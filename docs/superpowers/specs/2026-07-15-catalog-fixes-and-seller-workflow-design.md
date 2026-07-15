# Product Images, Related Products, Seller Login & Edit-Acceptance — Design Spec

Date: 2026-07-15
Status: Approved

## Purpose

Fix a batch of issues found in a status audit against the original requirements list:
missing single-primary-image enforcement, no hero/thumbnail carousel on the product
page, inconsistent (or absent) small-thumbnail image sizing across the site and the
RFQ email, an inert (non-clickable) related-products section, a crash on seller
portal login, no discoverable path from the public site into seller self-registration,
and a missing Admin-edit / seller-acceptance workflow around product review.

## A. Product Images (single primary, hero carousel, consistent thumbnails, related-products link)

**Single primary image.** `ProductImage` gets a `saved` model event: whenever a row
is saved with `is_primary = true`, every sibling image for that `product_id` (any
other row) gets `is_primary` set to `false` in the same request. This lives on the
model itself (`app/Models/ProductImage.php`), so it automatically covers both the
Admin (`app/Filament/Resources/ProductResource/RelationManagers/ImagesRelationManager.php`)
and Seller relation managers, since they already share that one component.

**Fallback resolution.** New `Product::primaryImage(): ?ProductImage` — returns the
image flagged primary, or falls back to the first image by `sort_order` if none is
flagged yet. This is the one method used everywhere a "the" product image is needed:
hero image, related-product thumbnails, homepage featured blocks, and the RFQ email.
No caller re-implements the fallback logic.

**Hero + thumbnails.** The product detail Blade view
(`resources/views/catalog/product.blade.php`) replaces its current plain stacked
`<img>` loop with a Bootstrap Carousel (already loaded via CDN per `CLAUDE.md`):
auto-advancing, prev/next arrows, opening on `primaryImage()`, with thumbnail
indicators built from `$product->images`.

**132×132px thumbnails, applied consistently.** A new Blade component
`resources/views/components/product-thumbnail.blade.php` (`<x-product-thumbnail>`)
wraps an `<img>` at a fixed `132×132px` with `object-fit: cover`. Used in:
- Related-products cards (currently render no image at all).
- Homepage featured-product/featured-category blocks
  (`resources/views/blocks/featured_products.blade.php`,
  `resources/views/blocks/featured_categories.blade.php`), replacing their current
  responsive `card-img-top` sizing.
- The RFQ/quote-request email (`resources/views/emails/quote-request-received.blade.php`),
  which currently includes no product image — inline `width`/`height` HTML
  attributes are added alongside the CSS, since email clients need both for
  reliable rendering.

**Related-products link bug.** `resources/views/catalog/product.blade.php`'s
related-products loop wraps each card in an `<a href="...">` to the product's own
page, matching the existing pattern already used correctly in
`featured_products.blade.php`, and adds the new thumbnail component.

## B. Seller Login Crash

Root cause (confirmed from a real stack trace): `Seller` has no `name` attribute and
doesn't implement Filament's `HasName` contract, so
`Filament\FilamentManager::getUserName()` calls
`$user->getAttributeValue('name')`, gets `null`, and fatals against its `string`
return type the moment any Filament panel chrome (the account widget / avatar) tries
to render — i.e. immediately after a successful login, when the dashboard loads.

Fix: `App\Models\Seller implements Filament\Models\Contracts\HasName`, adding
`getFilamentName(): string` that returns `contact_person`, falling back to
`company_name` if ever blank. Covered by a feature test that logs a seller in and
asserts the dashboard route returns 200 (the existing `SellerPanelAccessTest` only
checks `canAccessPanel()`, not that the dashboard actually renders — this was the
gap that let the bug ship unnoticed).

## C. Seller Discoverability Gap

The public nav's "Login as Seller" link goes straight to the Filament seller login
page, and nothing links from the public site (or that login page) into
`/seller/register`. Rather than build a new marketing landing page competing with
`/seller` (already the Filament panel's own route), add a "New seller? Register
here" link directly on the Filament seller panel's login page content (Filament
supports customizing panel login page view/slots) pointing at the existing
`seller.register` route. No new routes.

## D. Admin Edit-Trail & Seller Acceptance

Scoped to **initial review only** — a product in `pending_review` status. Edits to
an already-`published` product (Content Editor cleanup, per `CLAUDE.md`) are
untouched by this feature; the gate never fires from any status other than
`pending_review`.

**Data model.** New `product_edit_trails` table:
- `id`, `product_id` (FK → products), `staff_id` (FK → staff, nullable-safe),
  `changes` (JSON: `{field: [old, new], ...}`), `created_at`, `accepted_at`
  (nullable, set when the seller accepts).
- New `App\Models\ProductEditTrail` — `belongsTo(Product)`, `belongsTo(Staff)`.
- `Product::editTrails(): HasMany` and `Product::latestPendingEditTrail(): ?ProductEditTrail`
  (the most recent trail row with `accepted_at IS NULL`).

**Tracked fields** for the diff: `name`, `slug`, `sku`, `short_description`,
`description`, `features`, `applications`, `spec_sheet_path`, `category_id`,
`quantity`. Deliberately excludes `price_display`/`status` — those are Admin-only
fields the seller never controls, per the existing pricing-workflow rule, so a
price change alone must never trigger this gate.

**Admin `EditProduct` page** (`app/Filament/Resources/ProductResource/Pages/EditProduct.php`):
`mutateFormDataBeforeSave()` — only when `$this->record->status === 'pending_review'`,
diff the incoming `$data` against `$this->record`'s current values for the tracked
fields. If any differ: build the `changes` array, create a `ProductEditTrail` row
(`staff_id` = the acting admin), force `$data['status'] = 'pending_seller_acceptance'`
(overriding whatever the form's status field held), and best-effort-email the seller
a new `ProductEditReadyForAcceptance` mailable containing the diff (same
try/catch-and-log pattern as every other mailable in this codebase). If nothing in
the tracked fields changed, `$data` passes through unmodified and the product stays
`pending_review` exactly as today.

**New status value:** `pending_seller_acceptance`. `products.status` is a plain
string column (no DB enum), so this needs no migration — only code-side updates:
- The status `Select` in the Admin form needs a disabled-but-visible option for
  `pending_seller_acceptance`, mirroring the existing pattern already used for
  `published` (shown when that's the record's current value, never selectable).
- The table's `publish` action (`ProductResource.php:115-118`) is currently visible
  purely based on the `approve` policy check, with **no status condition at all** —
  meaning as written today it would still be clickable while a product sits in
  `pending_seller_acceptance`, bypassing the acceptance gate entirely. Its
  `visible()` closure gets an added `$record->status !== 'pending_seller_acceptance'`
  condition so Admin cannot force-publish over an unaccepted edit.

**"Page live" notification (7.2).** The existing `publish` action's `action()`
closure, in addition to calling `$record->publish()`, best-effort-sends a new
`ProductListingLive` mailable to the seller ("your listing is live"). This is the
no-edits-made path.

**Seller acceptance.** New table action on the seller `ProductResource`
(`app/Filament/Seller/Resources/ProductResource.php`), visible only when
`status === 'pending_seller_acceptance'`: opens a modal/page showing the diff from
`latestPendingEditTrail()`, with an "Accept Changes" button. On accept: stamp
`accepted_at` on the trail row, call `$record->publish()` (still enforces
`price_display` is set — unchanged invariant), and send the same
`ProductListingLive` mailable. This closes the loop for 7.1's "once accepted the
page will be live."

No reject/renegotiate path is in scope — only acceptance, matching the original
requirement's wording exactly (YAGNI on a counter-proposal flow that wasn't asked
for).

## Testing

Every new behavior gets a feature test first, per this repo's existing convention:
- `ProductImage` single-primary enforcement (creating/updating a second primary
  image unsets the first).
- `Product::primaryImage()` fallback (flagged image wins; falls back to
  earliest-by-sort_order when none flagged).
- Hero carousel and thumbnail component render correctly (structural assertion —
  primary image appears first, thumbnails present for each image).
- Related-products cards are wrapped in a working link (assert the href resolves to
  the product's own page).
- Seller login: a full round trip (login → dashboard 200), catching exactly the
  crash this spec fixes.
- Seller-register link is reachable from the seller login page.
- Edit-trail creation: admin edit to a `pending_review` product with a tracked-field
  change moves status to `pending_seller_acceptance`, creates the trail row with a
  correct diff, and sends the email; a no-op save (or a `price_display`-only change)
  leaves status untouched and creates no trail.
- `publish` action is hidden while `status === 'pending_seller_acceptance'`.
- Seller "Accept Changes" moves status to `published`, stamps `accepted_at`, and
  sends the live-notification email; blocked (as today) if `price_display` is
  unset.
- No-edit approve path still sends the live-notification email.
