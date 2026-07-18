# Design: GitHub issues #1–#7

Date: 2026-07-18

Fixes the seven open issues on `amt1010/inventory`. Each is implemented
test-first with `php artisan test` green before its commit; one commit per issue.

## #1 — Features & Applications as Rich Text

Currently `Repeater` fields stored as JSON arrays (`features`, `applications`),
authored one bullet at a time. Description is already a `RichEditor`.

- Replace both repeaters with `RichEditor` in the admin `ProductResource` and the
  seller `ProductResource`.
- Drop the `features`/`applications` `array` casts on `Product`; the columns
  become nullable HTML text.
- Migration converts any existing array data to `<ul><li>…</li></ul>` HTML so no
  content is lost, and normalises the columns to `TEXT`/nullable.
- Public `product.blade.php` renders `{!! $product->features !!}` /
  `{!! $product->applications !!}` instead of the `<ul><li>` loops.
- Update `ProductFactory` and affected tests.

## #2 — Dedicated Category section (Admin + Seller)

Applies to both Admin and Sellers; the product inherits the selected leaf
category. Approval journey unchanged: a seller's category + sub-category +
product bundle is reviewed by Admin together, and Admin can override.

- **Admin** `CategoryResource` list: render the tree as indented parent → child
  rows (ordered by hierarchy then `sort_order`). N-level create already works.
- **Seller** new `App\Filament\Seller\Resources\CategoryResource`:
  - Create N-level categories/sub-categories; each lands `status = 'draft'`,
    `proposed_by_seller_id = self`.
  - Parent options: published categories + the seller's own draft proposals.
  - Edit/delete allowed only for the seller's own not-yet-published proposals.
  - Authorization via seller-scoped `canX()` static methods (same pattern as the
    seller `ProductResource`), not the Staff-typed `CategoryPolicy`.
- The product form's inline "create category" combo remains as a convenience.

## #3 — Primary image toggle fix

Symptom: toggling "primary" on an image uploaded earlier (not primary at upload
time) does not take effect. Reproduce with a failing test that uploads a
non-primary image, then edits it to `is_primary = true`, and asserts it becomes
the sole primary. Fix root cause; keep the regression test.

## #4 — Price ₹ prefix + Indian comma grouping

`price_display` is admin-only free text holding ranges + words
(`₹1,200 – ₹1,800 per reel`).

- Add `->prefix('₹')` to the field.
- Add an Alpine input handler that regroups runs of digits into Indian style
  (`1,00,000`) live while leaving separators/words intact.
- Client-side formatting is verified by driving the admin form, not PHPUnit.

## #5 — Best-fit product images

Product-page main carousel image uses `object-fit: cover` (crops). Change to
`contain` on a neutral letterbox background so the whole image shows without
quality loss. Grid/related thumbnails stay `cover` for uniform tiles.

## #6 — Search includes Categories/Sub-categories

Search (Scout `database` driver) only indexes Products. Make `Category`
`Searchable` (name + description), index published categories, and show a
"Categories" result group above products on the search results page.

## #7 — Remove 'Select Market'

Full removal: the form field, the `market` validation rule, `config('rfq.markets')`,
the model `fillable` entry, and the `market` DB column (new migration). Update
`QuoteRequestFactory` and affected tests.
