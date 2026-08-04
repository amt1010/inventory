# Product Listing Modernist Redesign — Design Spec

Date: 2026-08-04
Status: Approved (pending user's final review of this document)

## Purpose

Apply the "Modernist" visual design system (established in
`docs/superpowers/specs/2026-08-04-homepage-modernist-redesign-design.md`,
phase 1) to the Product Listing page — the leaf-category product grid served
by the existing `CatalogController`/`catalog.category` view — and add real
sidebar filtering and sorting. This is phase 2 of the catalog reskin.

## Background / source material

Design handoff package's Product Listing mockup
(`Inventree_ProductListing_standalonev3.html`) plus its README section:
breadcrumb, sticky sidebar (category list, price range, minimum-order radios,
"Trade Assurance" checkbox, Apply Filters), results header (count + sort
dropdown), 3-column product grid (grayscale image, conditional "Deal" tag,
name/MOQ/price/supplier, "Add to RFQ" button), numbered pagination.

Several of the mockup's concepts don't map to anything real in this codebase
and were resolved during brainstorming (see "Scope decisions" below) the same
way homepage's mismatches were: drop what has no backing data, keep what does.

## Scope decisions (already made with the user)

- **Applies to the existing `catalog.category` view**, specifically the
  branch that renders a leaf category's product grid (`$products`). The
  hub/tile branch (categories with children, shown when `$children` is
  non-empty) is unchanged — it keeps showing child-category tiles, not a
  product grid or filters. The separate search-results page
  (`catalog.search`) is out of scope for this phase.
- **Pagination stays infinite-scroll**, not the mockup's numbered
  Prev/1/2/3/Next. The existing IntersectionObserver + AJAX-partial mechanism
  (shipped just before this phase) is restyled, not replaced or rebuilt.
- **No Price Range filter.** `products.price_display` is free text (e.g.
  "₹45/meter"), not a numeric column — a real min/max filter isn't feasible
  without a schema change, which is out of scope here.
- **No "Trade Assurance" filter.** The concept doesn't exist anywhere in this
  codebase's domain model (no verification/escrow feature), and CLAUDE.md
  rules out payment-adjacent features entirely. Dropped, not built.
- **No "Deal" tag on cards.** Same resolution as the homepage's deals
  banner — no `deal` flag exists on `Product`, so no per-card badge is shown.
- **No supplier/seller name on any card.** Per CLAUDE.md's public-page rule
  (already enforced on every homepage block; carried over here unchanged).
- **Sidebar filters are driven by the existing `custom_attributes` table**
  instead of hardcoded Price/MOQ fields. Every distinct attribute `label`
  present among the current category's products becomes a filter group
  automatically (e.g. "Color", "Material Type") — no new schema, no Admin
  configuration step. Adding a new custom attribute to a product on the
  product detail/edit form makes it filterable on the listing page with zero
  code changes.
- **Sort is Newest / Name (A-Z) / Name (Z-A)**, not the mockup's price
  ASC/DESC (blocked by the same free-text-price constraint as the filter).
  Default stays the existing Admin-curated `sort_order`, matching current
  behavior when no sort is chosen.
- **"Add to RFQ" opens the existing per-product quote modal**
  (`partials/quote-request-form.blade.php`, already used on the product
  detail page) rather than a new multi-item "cart" flow — `QuoteRequest` is
  already strictly one-product-at-a-time (`product_id` is a single nullable
  FK), so a cart-style "add multiple items, submit once" flow has no backing
  data model and isn't built.

## Filter mechanics

Query-string driven, applied inside `CatalogController::show()`:

- `?attr[Color][]=Red&attr[Color][]=Blue&attr[Material][]=Copper` — checkbox
  selections. Values within one group are OR'd (`whereIn`); different groups
  are AND'd together (`whereHas` chained per group). A product matches a
  group if it has **any** `CustomAttribute` with that `label` and a `value`
  in the selected set.
- `?sort=newest|name_asc|name_desc` — maps to `orderBy('created_at', 'desc')`
  / `orderBy('name')` / `orderBy('name', 'desc')`; absent or unrecognized
  falls back to the existing `orderBy('sort_order')`.
- Checking a box submits immediately (a plain `<form>` with `onchange`
  submit, or equivalent) — no separate "Apply Filters" button, since every
  filter/sort change is a full (non-AJAX) page load that re-renders the
  sidebar's available groups/values against the new result set anyway.
- The sidebar's filter groups and their available values are always computed
  from **all published products in the category**, not narrowed by whichever
  filters are currently active. (Classic faceted search — where each group
  narrows to only the values still reachable given the *other* active
  filters — is more correct but meaningfully more complex; this phase uses
  the simpler static-sidebar behavior the mockup itself shows.)
- Infinite scroll's "next page" URL must preserve the active `attr[]`/`sort`
  query params (via the paginator's `withQueryString()`), and the AJAX
  partial endpoint (already handles `$request->ajax()` in
  `CatalogController::show()`) must apply the same filter/sort logic as the
  full-page request — it's the same controller method, so this falls out
  naturally rather than needing separate logic.

## Product cards

Same visual treatment as the homepage's restyled `featured_products` block:
`.md-card`, `.md-grayscale` image, product name, `MOQ: {quantity}`,
`price_display`, no supplier name. Card links to the product detail page
(unchanged URL scheme: `/products/{category-path}/{product-slug}`). "Add to
RFQ" is a second, separate button on the card (not the whole card) that opens
`#quoteRequestModal-{id}` — one modal instance rendered per card in the grid,
reusing `partials/quote-request-form.blade.php` exactly as the product detail
page does. This is already collision-safe: the modal id and every form field
id inside it key off `$product->id}`, so N products on one page produce N
non-colliding modals with no changes needed to that partial.

## Testing

- Sidebar filter groups: a category with products carrying different
  `custom_attributes` labels/values renders one group per label with the
  correct distinct values; a category with no custom attributes renders no
  filter groups (not an empty/broken sidebar section).
- Filtering: selecting a value narrows results to matching products only;
  selecting values in two different groups requires a product to match both
  (AND across groups); selecting two values in the same group matches either
  (OR within group).
- Sorting: each of the three sort options changes result order correctly;
  no `sort` param falls back to the existing `sort_order` behavior.
- Infinite scroll: the next-page URL (and the AJAX partial response it
  fetches) preserves active filter/sort query params — scrolling for more
  results doesn't silently drop them.
- The "Add to RFQ" modal opens per card and submits identically to the
  product detail page's existing modal (reuses `QuoteRequestSubmissionTest`
  coverage patterns — same route, same validation).
- No supplier/seller name ever renders on any card (same style of assertion
  as the homepage's `FeaturedProductsCardDetailsTest`).
- Existing catalog tests that must keep passing unchanged:
  `tests/Feature/CatalogRoutingTest.php`, `tests/Feature/CatalogSearchTest.php`
  (search page untouched), and the hub/tile-view behavior for categories with
  children.
