# Category & Product Bulk Import — Design Spec

Date: 2026-08-23
Status: Approved
Issue: https://github.com/amt1010/inventory/issues/33

## Purpose

Admin can currently only create categories and products one at a time through
their manual create forms. This feature lets Admin upload one Excel/CSV sheet
that creates a whole category tree (Parent Category → Sub-Category-1 →
Sub-Category-2) and, optionally, a Product at the leaf of that tree — all from
a single row. Nothing this importer creates is ever auto-published: new
categories land in `draft`, new products land in `pending_review`, exactly
where a manually-created one would sit before Admin reviews it.

This mirrors and reuses the seller-bulk-upload feature's own conventions
(`docs/superpowers/specs/2026-08-05-seller-bulk-upload-design.md`): a
placeholder string for blank-but-required cells, `created_by =
admin_bulk_upload`, and Filament's native `Importer`/`ImportAction`.

## Decisions Made (in order, from clarifying questions)

1. **Seller assignment.** `products.seller_id` becomes nullable. The sample
   sheet has zero rows with a Seller filled in — bulk-imported products are
   never assigned a seller by the importer itself, regardless of what (if
   anything) is in a sheet's Seller column. Admin assigns the seller later,
   manually, via the existing product edit form's `seller_id` Select
   (`ProductResource::form()`), which already exists and is already
   `->required()` — no form change needed there. `Product::publishBlockers()`
   gains a new check: a product with no seller cannot be published.
2. **Status.** Reuse the existing `pending_review` status — no new Product
   status is introduced. This matches how every other not-yet-published
   product already works, and mirrors the seller-import precedent's own
   principle (reuse an existing status where a conceptual equivalent already
   exists, rather than invent one).
3. **Audit logging.** A new, dedicated `AuditLog` model/table/admin page
   covers **every** bulk importer going forward — both the existing
   `SellerImporter` and the new `CategoryProductImporter` — since Filament's
   own `imports` table has a known, documented gap (it can't reliably record
   which Admin ran an import under the `staff` guard; see the seller spec's
   Task 6 notes) and requirement #7 explicitly wants the login used to
   import.
4. **Import structure.** One combined importer. Each row can create a
   category chain and, optionally, a product at its leaf — matching the
   attached sample sheet's flat row-per-product layout exactly.

## Data Model

Three additive migrations (this dev/production database holds real category
and product data and is never reset via `migrate:fresh` for this kind of
change — verify every migration with `php artisan migrate`, never
`migrate:fresh`, per `CLAUDE.md`):

- **`products`**: `seller_id` foreign key becomes nullable (drop + re-add the
  constraint as nullable — MySQL/SQLite both require this two-step change on
  an existing FK column). Add `material_type` — `string`, **not** nullable,
  values `raw_material` or `finished_good`, backfilled to `raw_material` for
  every existing row in the same migration (a real, if arbitrary, default —
  matches how the seller migration backfilled `seller_code` for existing rows
  rather than leaving a not-null column with no data). Add `created_by` —
  `string`, nullable (existing rows stay `null`; only bulk-imported rows ever
  get a value, unlike `sellers.created_by` which is not-null with a
  `self`/`admin` default — there's no equivalent "who created this" concept
  for manually-created products today, and inventing one for every existing
  and future manually-created product is out of scope for this issue).
- **`categories`**: add `created_by` — `string`, nullable, same reasoning as
  products above.
- **`audit_logs`** (new table): `id`, `importer_label` (`string` — a
  human-readable name like `"Category & Product Import"` or `"Seller
  Import"`, not a raw class name), `performed_by_staff_id` (`foreignId`,
  nullable, `nullOnDelete` — the staff member who triggered the import; `null`
  if somehow unavailable rather than blocking the import), `file_name`
  (`string`), `total_rows` (`unsignedInteger`, nullable until the import
  completes), `successful_rows` (`unsignedInteger`, nullable), `failed_rows`
  (`unsignedInteger`, nullable), `summary` (`text`, nullable — a short
  plain-English recap, e.g. `"52 categories and 31 products imported, 3 rows
  failed."`), `filament_import_id` (`unsignedBigInteger`, nullable — links
  back to Filament's own `imports.id` row so the completion hook can find
  which `AuditLog` row to update; no FK constraint, since `imports` is a
  vendor-owned table this app doesn't control the lifecycle of), timestamps.

## The "TO BE ADDED" Placeholder

A new constant, `Category::PLACEHOLDER = 'TO BE ADDED'` (this exact casing —
requirement #8 specifies it verbatim, unlike the seller feature's mixed-case
`'To be Added'`). Used whenever a row implies a category level should exist
(a sub-category or product name is present) but that level's own name cell is
blank. There is no equivalent `Product::PLACEHOLDER`: when the Product NAME
cell itself is blank, no product row is created at all (see the row algorithm
below) — a placeholder product name is never needed.

## Row Processing Algorithm

Implemented as a plain PHP service, `App\Services\CategoryChainResolver`, unit
tested independently of Filament's import machinery, and called from
`CategoryProductImporter::resolveRecord()`:

1. Read the row's `PARENT CATEGORY NAME`, `Sub-Category-1 Name`,
   `Sub-Category-2 Name` cells (descriptions are optional companions to each,
   stored only when that level is newly created — never overwritten on an
   already-existing category, per decision below).
2. Walk the chain top-down. For each present level (Parent is always
   considered "present" if the row has ANY category/sub-category/product data
   at all; Sub-Category-1/2 are only considered present if that cell, or a
   deeper cell, is non-blank):
   - If the cell is blank but the level is implied (a deeper level or the
     product name is present), use `Category::PLACEHOLDER` as its name.
   - Look for an existing category matching `(name normalized
     case-insensitively, parent_id)` — **not** a global name match, since two
     different branches of the tree may legitimately share a sub-category
     name (mirrors the existing `unique(['parent_id', 'slug'])` DB
     constraint already enforced on `categories`).
   - If found, reuse it **unchanged** — never touch its `status`, `name`, or
     `description`, even if the row's description cell differs (requirement
     #4: an existing category's state is never altered by import).
   - If not found, create it: `status = 'draft'`, `created_by =
     'admin_bulk_upload'`, `slug = Str::slug($name)`, deduplicated against
     sibling slugs by appending `-2`, `-3`, ... on collision (`categories`'
     manual form only *validates* slug uniqueness interactively and rejects a
     collision — there's no human in the loop during bulk import to fix one,
     so the resolver must generate a guaranteed-unique slug itself),
     `description` from the row's matching description cell (or `null`),
     `parent_id` from the previous level resolved (or `null` for the top
     level).
3. The final resolved category (deepest level actually present in the row) is
   the row's leaf category.
4. If `Product NAME` is blank: stop here. The category chain above is still
   created/reused as needed (requirement #2) — `resolveRecord()` returns
   `null`, which Filament's `Importer::__invoke()` treats as "skip this row"
   with no error and no row counted as failed (confirmed in
   `vendor/filament/actions/src/Imports/Importer.php:53-57`).
5. If `Product NAME` is present: look for an existing `Product` matching
   `(name normalized case-insensitively, category_id = leaf category's id)`.
   If found, skip — `resolveRecord()` returns `null`, no update, no duplicate
   created (requirement #9: "no import needs to be done and no override is
   needed"). If not found, `resolveRecord()` returns `new Product()` with
   `category_id` already set to the leaf category's id; the rest of the
   product's fields (name, sku, short description, etc.) are filled by
   ordinary `ImportColumn`/`fillRecordUsing` mapping, and `beforeCreate()`
   sets `status = 'pending_review'`, `seller_id = null`,
   `created_by = 'admin_bulk_upload'`, and `material_type` from the row's
   `TYPE` cell (`"Raw Material"` → `raw_material`, `"Finished Good"` or
   `"Finished Goods"` → `finished_good`, matched case-insensitively and
   tolerant of a trailing "s" — the attached sample sheet uses "Finished
   Goods", the issue text says "Finished Good"). A blank or unrecognized
   `TYPE` cell fails that row's validation (`material_type` is a required,
   non-nullable column) — it is surfaced to Admin in Filament's normal
   per-row failure report, not silently guessed.

## Product Model / Policy Changes

- `Product::publishBlockers()` gains a third check, alongside the existing
  price and category-published ones: `blank($this->seller_id)` →
  `"Assign a seller on the product's edit form before publishing."`.
- `ProductResource::EditProduct::mutateFormDataBeforeSave()` currently emails
  `$this->record->seller->email` whenever a `pending_review` product's
  tracked fields change (to notify the seller their already-submitted listing
  was edited and needs re-acceptance). A bulk-imported product has no seller
  yet, so this must not fire — the null-seller case is not a business
  decision to make, it is a direct, unambiguous consequence of decision #1
  above: add `|| $this->record->seller_id === null` to the existing early
  return (`if ($this->record->status !== 'pending_review') { return $data; }`
  becomes `if ($this->record->status !== 'pending_review' ||
  $this->record->seller_id === null) { return $data; }`), so editing a
  seller-less product's tracked fields is a plain save with no edit-trail/
  email side effect, exactly as if it weren't `pending_review`-gated at all.
- `ProductFactory` gains a `material_type` default (`randomElement(['raw_material', 'finished_good'])`)
  since the column is not-nullable.

## Bulk Upload Mechanism

Built on the same `filament/actions` `Importer`/`ImportAction` already in use
(`Import::polymorphicUserRelationship()` is already enabled in
`AdminPanelProvider::boot()` from the seller-import feature — no repeat work
needed there).

- `App\Filament\Imports\CategoryProductImporter extends Importer`, columns
  mapped to the sample sheet's headers: `Product NAME`, `TYPE`, `PARENT
  CATEGORY NAME`, `PARENT CATEGORY Description`, `Sub-Category-1 Name`,
  `Sub-Category-1 Description`, `Sub-Category-2 Name`, `Sub-Category-2
  Description`, `SKU / Product Number`, `Product Short Description`, `Product
  Feature`, `Product Application`, `Price Range (INR)`, `Quantity`. (The
  sample sheet's `Seller`, `REMARKS`, and `Specification Sheet (PDF)` columns
  are not mapped — Seller per decision #1 above; REMARKS has no matching
  field on either model; a PDF file path can't come from a text cell in a
  bulk sheet the same way a single manual upload works, and re-plumbing file
  uploads through bulk import is out of scope for this issue.)
- An "Import Categories & Products" action is added to the `ListProducts`
  page header (`/admin/products`) — Products is where this action lives
  because every row's ultimate subject is a product (or, for a
  category-only row, a placeholder for one still to come); Categories has no
  equivalent action.
- `getCompletedNotificationBody(Import $import)` (already a required override
  on every `Importer`) both builds the notification text Admin sees (Filament
  shows this automatically — this already satisfies requirement #6's
  "pop-up... summary of failed and imported data", no separate UI needed) and
  calls `AuditLog::recordCompletion($import, 'Category & Product Import')`
  (see below).

## Audit Logs

- `App\Models\AuditLog`, table `audit_logs` (see Data Model above).
- `AuditLog::recordCompletion(Import $import, string $importerLabel): void` —
  a static helper called from both `SellerImporter::getCompletedNotificationBody()`
  and the new `CategoryProductImporter::getCompletedNotificationBody()`.
  Finds the matching `AuditLog` row by `filament_import_id = $import->id` and
  fills in `total_rows`, `successful_rows`, `failed_rows` (from
  `$import->getFailedRowsCount()`, same method the existing notification body
  already calls), and a generated `summary` string. This runs wherever
  `getCompletedNotificationBody()` runs (same process/context Filament
  already uses for the existing seller notification, so no new reliability
  concern is introduced) — it only performs a DB update keyed by
  `filament_import_id`, so it needs no `auth()` call and works correctly
  whether that context has session/guard access or not.
- The **row is created**, capturing `performed_by_staff_id`, at import
  *dispatch* time instead of completion time — the one moment
  `auth('staff')->user()` is guaranteed available, since it's the same
  request/response cycle as the Admin clicking "Import" in their browser.
  Hooked via `Filament\Actions\Imports\Models\Import::created(...)` in
  `AdminPanelProvider::boot()`, right next to the existing
  `Import::polymorphicUserRelationship();` line — reading
  `$import->importer` (the FQCN Filament already stores on every `Import`
  row) to pick a friendly `importer_label` (`SellerImporter::class =>
  'Seller Import'`, `CategoryProductImporter::class => 'Category & Product
  Import'`), and `auth('staff')->user()?->id` for `performed_by_staff_id`.
- `App\Filament\Resources\AuditLogResource` — read-only (no create/edit/
  delete pages or actions, `canCreate()` returns `false`), navigation label
  "Audit Logs", listing every column above (`performed_by.name`, `file_name`,
  `importer_label`, counts, `summary`, `created_at`), newest first. Gated by a
  new `audit_logs` permission area (see below) — `admin` gets `full`,
  `content_editor`/`sales` get nothing, matching how only Admin can trigger
  either bulk-import action today.

## Permissions

`database/seeders/RoleSeeder.php`'s `AREAS` constant gains `'audit_logs'`.
`ROLE_MATRIX`: `admin => 'full'`; `content_editor` and `sales` are not given
this area at all (same pattern as `staff`/`settings`, which only `admin`
holds).

## Testing

Every new behavior gets a feature test first, per this repo's convention:

- **Migrations**: `products.seller_id` accepts `null`; `products.material_type`
  is not-nullable and existing rows are backfilled to `raw_material`;
  `products.created_by` and `categories.created_by` exist and default to
  `null`; `audit_logs` table exists with the columns above.
- **`CategoryChainResolver`**: resolves/creates a 1-, 2-, and 3-level chain
  from blank state; reuses an existing category unchanged (name, description,
  status untouched) when the same name+parent already exists; fills
  `Category::PLACEHOLDER` for a blank name at an implied level; two different
  parents can each have a child of the same name without collision; new
  categories are `draft` with `created_by = 'admin_bulk_upload'`.
- **`CategoryProductImporter`**: a row with no Product NAME creates only the
  category chain, no product; a row whose resolved product name+category
  already exists creates nothing new for that row (no duplicate, no update);
  a fully-populated new row creates the category chain and a `pending_review`
  product with `seller_id = null`, `created_by = 'admin_bulk_upload'`, and the
  correct `material_type` for `"Raw Material"` / `"Finished Goods"` /
  `"Finished Good"` (case-insensitive); a blank/unrecognized `TYPE` cell fails
  that row.
- **`Product::publishBlockers()`**: includes the seller-missing reason when
  `seller_id` is null; existing price/category blockers are unaffected.
- **`EditProduct`**: editing a `pending_review` product with no seller does
  not send any email and does not create an edit trail; existing
  seller-present behavior for `pending_review` products is unchanged.
- **`AuditLog`**: an `Import::created` event for either importer creates an
  `AuditLog` row with the acting staff's id and a friendly `importer_label`;
  `AuditLog::recordCompletion()` fills in the counts/summary on the matching
  row by `filament_import_id`; the `/admin/audit-logs` page is visible to
  `admin` and not to `content_editor`/`sales`, and lists a completed import's
  data.
