# Seller Bulk Upload — Design Spec

Date: 2026-08-05
Status: Approved

## Purpose

Admin can currently only create sellers one at a time via `/admin/sellers`'
manual create form. This feature lets Admin upload a CSV of many prospective
sellers at once — each row becomes a `Seller` record with `status =
pending_admin_approval`. Rows with blank cells are not rejected; the blank
field is filled with a literal placeholder (`"To be Added"`) so the row still
imports, and Admin completes the missing data later by editing the record.
Approval is gated: a seller (bulk-imported or not) cannot move to `approved`
while any required field still holds the placeholder, or while its email/GST
number conflicts with another seller.

Two new fields (Manufacturing Activity, Availability Hours) are added
everywhere a seller is created — self-registration, Admin manual-create, and
bulk upload — not just the bulk-upload path. A new human-readable Seller Code
is also introduced for every seller, on all three creation paths.

## Data Model

All changes are one additive migration to `sellers` (plus a same-migration
backfill of existing rows — this dev/production database holds real seller
data and is never reset via `migrate:fresh` for this kind of change):

- **`seller_code`** — `string(16)`, unique, not null. Format
  `YYMMDDHHMM` + literal `S` + a 5-digit zero-padded sequence, e.g.
  `2608051423S00042`. The sequence digits reuse the seller's own
  auto-increment `id` (already globally unique and strictly increasing), so no
  separate counter table is needed. Set in a `Seller::created` model event,
  since `id` isn't known before insert. *Assumes fewer than 100,000 sellers
  ever exist; if that's exceeded the 5-digit sequence would need widening —
  not built now (YAGNI), just flagged.* Existing rows are backfilled in the
  same migration using their own `id`.
- **`manufacturing_activity`** — `string`, nullable.
- **`availability_hours`** — `string`, nullable.
- **`email`** — the existing `unique` constraint is dropped. Bulk-imported
  rows may hold the placeholder or a comma-separated list of addresses before
  Admin cleans them up; uniqueness becomes a business rule enforced only at
  approval time (see "Approval Gate" below), not a DB constraint.
- **`created_by`** — no schema change (still a plain string column); gains a
  third accepted value, `admin_bulk_upload`, alongside the existing
  `self`/`admin`.

No change to `products`. `products.seller_id` already links to the owning
seller; the Admin `ProductResource` table/detail view gains one read-only
column showing `$record->seller->seller_code`, exactly like every other
seller-identifying field in that resource — internal/Admin-only, never
surfaced on any public page, consistent with CLAUDE.md's "seller identity
stays internal" rule.

## The "To be Added" Placeholder

A constant, `Seller::PLACEHOLDER = 'To be Added'`. When the bulk-upload
importer encounters a blank cell for any of `company_name`, `contact_person`,
`phone`, `business_address`, `gst_number`, `manufacturing_activity`,
`availability_hours`, or `email`, it stores this literal string rather than
`NULL` or an empty string. Non-blank cells (including a comma-separated email
list) are stored as-is, trimmed.

## Approval Gate

Today, `SellerResource`'s `approve` table action
(`app/Filament/Resources/SellerResource.php:92-110`) unconditionally sets
`status = approved` with no readiness check. This becomes a new invokable
action class, `App\Actions\ApproveSeller`, used by that same button (and
available for any future bulk-approve action). It mirrors how
`Product::publish()` centralizes its own single gating rule rather than
re-implementing it ad hoc.

`ApproveSeller` refuses to approve (returning the list of blocking reasons,
shown to Admin via a Filament notification) unless, for the seller being
approved:

1. None of `company_name`, `contact_person`, `phone`, `business_address`,
   `gst_number`, `manufacturing_activity`, `availability_hours` equal the
   placeholder.
2. `email` contains exactly one syntactically valid address (no comma) and
   does not equal the placeholder.
3. No other seller in the table shares this `email`.
4. No other seller in the table shares this `gst_number`.

Because manually-created sellers (self-registered or Admin manual-create)
never receive the literal placeholder text, checks 1–2 are a no-op for them
unless a field was genuinely left blank as the placeholder string itself
(which normal forms never produce) — in practice this gate only meaningfully
constrains bulk-uploaded rows. Checks 3–4 apply uniformly to every approval,
bulk-uploaded or not.

## Bulk Upload Mechanism

Built on Filament v3's native `Importer`/`ImportAction`
(`filament/actions` ^3.2, already installed — confirmed present under
`vendor/filament/actions/src/Imports/`). No new Composer dependency.
CSV only (not `.xlsx`).

- `App\Filament\Imports\SellerImporter extends Importer`, with one
  `ImportColumn` per Excel/CSV header, mapped to the matching `Seller`
  field:

  | File column | Seller field |
  |---|---|
  | Company Name | `company_name` |
  | Manufacturing Activity | `manufacturing_activity` |
  | Address | `business_address` |
  | Phone | `phone` |
  | Email | `email` |
  | Availability Hours | `availability_hours` |
  | Contact Person | `contact_person` |
  | GST Number | `gst_number` |

  Blank-cell handling applies the placeholder rule above.
- Every imported row is created with `status = pending_admin_approval` (the
  existing status value — no new status is introduced, since bulk-uploaded
  sellers are conceptually "awaiting admin review" exactly like any other
  pending seller) and `created_by = admin_bulk_upload`.
- An "Import Sellers" action is added to the `ListSellers` page header
  (`/admin/sellers`), giving Admin Filament's standard upload dialog with
  its built-in progress and per-row error/skip reporting. This requires
  publishing Filament's `imports`/`failed_import_rows` migrations
  (`vendor/filament/actions/database/migrations/`) as part of implementation.
- Rows whose email or GST number matches an existing seller are imported
  as-is — no reject-at-import-time duplicate check. Duplicates are instead
  caught by the Approval Gate (checks 3–4 above) when Admin tries to approve
  that row.

## Activation Email Flow for Bulk-Uploaded Sellers

The existing activation link (`app/Http/Controllers/Seller/
ActivationController.php`) only works while `status ===
pending_email_verification`, and for Admin-manual-created sellers, submitting
a password there immediately sets `status = approved`
(`ActivationController.php:38-43`) — bypassing the approve button and any
readiness check entirely. That shortcut is wrong for bulk-uploaded sellers,
who must pass the Approval Gate first.

So bulk-uploaded rows skip `pending_email_verification` entirely (there's no
verified email yet, possibly not even a real one) and start directly at
`pending_admin_approval`. `ActivationController` gains a new branch: a
seller with `created_by === admin_bulk_upload`, `status === approved`, and no
password set yet is also allowed to use the set-password view/route (today
gated to `pending_email_verification` only). The signed set-password link is
generated and emailed from inside `ApproveSeller` at the moment approval
succeeds, folded into the existing `SellerApproved` mail (conditional on the
seller having no password yet) rather than a new mailable. This means a
bulk-uploaded seller receives no email at all until Admin has filled in every
required field and clicked Approve — never an email sent to a placeholder or
multi-address value.

## Manual Forms

Both `StoreSellerRegistrationRequest` (self-registration) and
`SellerResource::form()` (Admin manual-create) gain `manufacturing_activity`
and `availability_hours` inputs, both nullable/optional on both forms. (Note:
self-registration's existing `business_address`/`gst_number` are currently
*required* there — `StoreSellerRegistrationRequest.php:22-23` — while the
Admin form leaves them optional; that pre-existing inconsistency is untouched.
The two new fields are optional on both forms per this design, regardless of
that difference.)

## Testing

Every new behavior gets a feature test first, per this repo's convention:

- Migration: `seller_code`, `manufacturing_activity`, `availability_hours`
  columns exist with correct nullability; `email` no longer has a unique
  index; existing seeded sellers are backfilled with a valid `seller_code`.
- `seller_code` is generated in the correct format and is unique across all
  three creation paths (self-registration, Admin manual-create, bulk upload).
- CSV import creates one `Seller` per row with `status =
  pending_admin_approval`, `created_by = admin_bulk_upload`.
- Blank cells in an uploaded row become the literal placeholder; non-blank
  cells (including a comma-separated email) are stored as given.
- `ApproveSeller` blocks approval and lists the reason when: any field still
  holds the placeholder; email has more than one address; email or GST
  number duplicates another seller. It's a no-op restriction for a
  manually-created seller with all fields genuinely filled in.
- `ApproveSeller` succeeds, sets `approved_at`/`approved_by`, and sends
  `SellerApproved` (with a set-password link when the seller has no password
  yet) once every check passes.
- A bulk-uploaded, now-`approved` seller can use the set-password link even
  though `status` is no longer `pending_email_verification`; an
  Admin-manual-created seller's existing auto-approve-on-password-set
  behavior is unchanged.
- Self-registration and Admin manual-create forms accept the two new fields
  as optional and persist them; omitting them still succeeds.
- Admin's Product list/detail shows the owning seller's `seller_code`.
