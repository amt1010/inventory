# Seller Category Proposals — Design Spec

Date: 2026-07-15
Status: Approved

## Purpose

Sellers listing a product are currently restricted to picking an existing,
Admin-published leaf category (`app/Filament/Seller/Resources/ProductResource.php`
form: `Category::query()->whereDoesntHave('children')->pluck('name', 'id')`). This
is too rigid for product families Admin hasn't anticipated. This feature lets a
seller either pick an existing category or propose a new one inline; the proposal
becomes a normal (unpublished) row in the existing category tree that Admin
reviews, corrects if needed, and publishes — at which point the seller's product
can proceed to final publish exactly as today.

This supersedes the "Sellers... do not create categories" line in `CLAUDE.md`'s
architecture map — see "Documentation update" below.

## Data Model

**`categories.proposed_by_seller_id`** — new nullable `foreignId` column,
`constrained('sellers')->nullOnDelete()`, added via a plain additive migration (no
change to any existing column). Tags a category row as seller-originated so Admin
can distinguish it from their own drafts and so the seller's own dropdown can
surface it back to them. `null` for every category created through the existing
Admin `CategoryResource` (including all pre-existing rows).

**No new status value.** A seller-proposed category is created with the *existing*
`status = 'draft'` — identical in meaning to an admin's own not-yet-published
category. `CatalogController` already scopes every tree lookup (children, root
categories, products) to `status = 'published'`
(`app/Http/Controllers/CatalogController.php:25,41,59-60,62`), so a `draft`
proposal — and, transitively, its parent if the parent had no other published
children — is automatically invisible to buyers with no new guarding code. This
also means the Admin `CategoryResource`'s existing status filter/column already
doubles as the review queue; no new Filament page or resource is built.

## Seller-Side UI

`app/Filament/Seller/Resources/ProductResource.php`'s `category_id` `Select`
gains:
- **Options**: existing leaf categories with `status = 'published'`, **plus** the
  seller's own leaf categories with `status = 'draft' AND proposed_by_seller_id =
  auth('seller')->id()` — so a seller can reuse a category they already proposed
  (e.g. for a second product) without re-proposing it, but never sees another
  seller's or Admin's unpublished drafts.
- **`createOptionForm()`**: a small inline form (Filament's native "+" affordance
  next to a searchable Select) with two fields — `name` (required) and
  `parent_id` (a `Select` of existing categories, any status the seller can see
  per the same visibility rule above, nullable — blank proposes a new top-level
  category, filled proposes a new sub-category under that parent). Both
  top-level and sub-category proposals are allowed, per the approved design.
- **`createOptionUsing()`**: creates the `Category` with `status = 'draft'`,
  `proposed_by_seller_id = auth('seller')->id()`, an auto-slugged `slug` (same
  `Str::slug()` pattern already used for product name→slug elsewhere in this
  form), and returns its `id`, which Filament auto-selects into the field.

The seller's product form also gains a read-only note, visible only when the
selected category is still `draft`: *"Category status: Draft — an administrator
needs to review and publish this category before your product can go live."*
(A `Placeholder`, following the same pattern as the form's existing
`status_display`/`rejection_reason` placeholders.)

## Admin-Side Review

No new Filament resource or page. Admin reviews a pending product as today; if
its category is still `draft`, Admin opens the existing `/admin/categories` list
(already filterable/sortable by status), corrects the proposed category's name,
slug, or parent if needed via the existing `CategoryResource` edit form exactly
as they would any other draft category, and sets its status to `published`. To
make "this came from a seller" visible at a glance, the Admin `CategoryResource`
table gains one column: `TextColumn::make('proposedBy.company_name')->label('Proposed
By')->placeholder('—')` (via a new `Category::proposedBy(): BelongsTo` relation to
`Seller`), so Admin can distinguish seller proposals from their own drafts without
opening each row.

## Final-Publish Gate

`Product::publish()` (`app/Models/Product.php`) currently only requires
`price_display` to be non-blank. It gains one more requirement: the product's
category must be `published`. This is the enforcement point for "category and
product are reviewed together, then final publish" — Admin cannot publish a
product whose category is still unresolved, regardless of whether they remember
to check it manually. This is a no-op for every product whose category was
already published the old way (the overwhelming majority, including every
existing seeded/test product), so it introduces no behavior change outside this
new proposal flow.

## Rejection

No new mechanism. If Admin decides a proposed category shouldn't exist (a
duplicate, a bad fit, an unclear name), they reject the *product* exactly as
today — `status = 'rejected'`, `rejection_reason` explaining why (e.g. "please
pick an existing category instead of proposing 'X'") — which the seller already
sees via the existing `rejection_reason` `Placeholder` on their product's edit
page. No new status, no new email. The now-orphaned draft category row is simply
left as an unpublished draft (harmless — invisible to buyers, and Admin can
delete it via the existing Categories screen if they want to tidy it up; deletion
is not automated, since a rejected product's category might still be a
legitimate proposal Admin wants to keep for a *different*, better-formed
resubmission).

## Documentation Update

`CLAUDE.md`'s Architecture map entry for `Category` currently reads (in part):
"Sellers pick from this existing tree when listing a product; they do not create
categories." This is updated to describe the propose-and-approve flow: sellers
may propose a new leaf category inline via the product form's category picker;
the proposal lands as an ordinary `draft` category tagged with
`proposed_by_seller_id`, invisible to buyers until an Admin reviews, optionally
corrects, and publishes it — after which the associated product's own review can
proceed to final publish.

## Testing

Every new behavior gets a feature test first, per this repo's convention:
- Migration: `proposed_by_seller_id` column exists, nullable, FK to `sellers`.
- `Category::proposedBy(): BelongsTo` relation.
- Seller can propose a new top-level category (no parent) via the create-option
  form; it's created as `draft`, tagged with their `seller_id`, and immediately
  selected on the product form.
- Seller can propose a new sub-category under an existing published parent.
- A seller's own draft proposal appears in their own dropdown on a second
  product; another seller's draft proposal does not.
- The "Category status: Draft" placeholder appears only when the selected
  category is draft, and is absent for a published category.
- Admin's Categories table shows the proposing seller's company name for a
  seller-proposed category, and shows the placeholder dash for an admin-authored
  one.
- `Product::publish()` returns `false` (and does not change status) when
  `price_display` is set but the category is still `draft`; returns `true` and
  publishes when both `price_display` is set and the category is `published`
  (the existing price-only test continues to pass unchanged, since it already
  uses a `published`-status category by default via the factory).
- A draft, seller-proposed category (and a published parent category whose only
  child is still draft) remain fully absent from every public catalog route —
  confirms the existing `CatalogController` scoping already covers this new
  proposal path with no additional guarding needed.
