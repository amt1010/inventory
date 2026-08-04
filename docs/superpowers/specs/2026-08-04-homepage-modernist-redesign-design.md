# Homepage Modernist Redesign — Design Spec

Date: 2026-08-04
Status: Approved (pending user's final review of this document)

## Purpose

Apply a new visual design system ("Modernist" — flat, 2px solid dividers, zero
border-radius, red `#ec3013` accent on `#f3f2f2` ground, Archivo font) to the
public Homepage, based on a Claude-Design handoff package (`Homepage.dc.html` /
`Inventree_Homepage_standalonev3.html` + accompanying `README.md` spec). This is
phase one of a larger reskin; the Product Listing page mockup exists but is
explicitly **out of scope for this phase** — the user's instruction was to build
and review the Homepage on `localhost:8000` first, and only push to the live
portal after explicit confirmation.

## Scope decisions (already made with the user)

- **Full chrome reskin, starting with Homepage.** The utility bar, header, and
  footer in `layouts/app.blade.php` — the one shared layout for the entire
  public site — get the new look now, not deferred to a later phase.
- **Mega-menu is untouched.** The `show_category_menu` dropdown branch in
  `layouts/app.blade.php` (the `$topLevelCategories`/`children` loop and
  Bootstrap's `data-bs-toggle="dropdown"` JS), and the simple-dropdown/plain-link
  branches next to it, keep their exact markup, data, and behavior. Only
  surrounding visual styling (typography/color/spacing) changes, via new CSS —
  no changes to the view composer, the Blade loop structure, or the JS.
- **Bootstrap stays.** New CSS is layered on top of the existing Bootstrap 5 CDN
  include; Bootstrap is not removed or replaced.
- **New sections are Page-Builder blocks, not a hardcoded template.** Consistent
  with the existing block-based CMS architecture and the prior redesign phase's
  "additive, not replacing, existing block types" convention (see
  `docs/superpowers/plans/2026-07-14-premium-storefront-redesign.md`). Content
  Editors keep full control of the homepage through `/admin`.
- **Newsletter signup gets a real backend** (new table + route) as part of this
  phase, rather than being decorative/non-functional.
- **The RFQ section uses the real, full quote-request form** (all required
  fields, validation, `/quote-requests` submission) — not the mockup's
  simplified 3-field version, since the backend has no lighter-weight path.
- **No supplier/seller name on any card.** The mockup shows a `supplier` name on
  every product/category card; CLAUDE.md is explicit that seller identity is
  never rendered on any public page. This line is dropped entirely. Category
  cards show a live published-product count instead of a supplier count.

## Global chrome changes (affects every page, not just `/`)

`layouts/app.blade.php` is shared by every page — catalog, RFQ history,
favorites, content pages, everything. Restyling it as part of "homepage first"
means the new header/utility-bar/footer ship site-wide as soon as this is
deployed, even though only the Homepage gets new *content* blocks. Calling this
out explicitly since the blast radius is bigger than "just the homepage" —
consistent with the user's process requirement, this all stays on
`localhost:8000` for review before anything goes live.

Changes:

- New utility bar row above the existing navbar: "Ship to: India | English" on
  the left (static text, no real i18n/shipping-zone feature — cosmetic only, per
  the mockup); "Become a Seller" (→ `route('seller.register')`, an existing
  route) and "Help Center" on the right — "Help Center" links to the CMS page
  with slug `help-center` if one exists (`@if (Page::whereSlug('help-center')
  ->where('status','published')->exists())`), otherwise that single link is
  omitted rather than pointing at a 404.
- Header restyled: logo, search bar, and nav links get the new Modernist look
  via `md-`-prefixed classes. The search form keeps posting `GET` to
  `route('catalog.search')`, unchanged.
- Footer restyled to the dark Modernist treatment, but keeps rendering the same
  dynamic data it does today (`$footerNavItems`, `$siteSettings` address/phone
  /email/social links, copyright text). The mockup's static "Marketplace" /
  "Company" link columns are **not** hardcoded in — `$footerNavItems` slots into
  whichever column layout the design calls for, so footer links stay editable
  via the existing `/admin` Nav Items screen rather than becoming dead links.

## New CSS layer

`public/css/modernist.css`, linked after `site.css`. Contains the design
tokens (color/spacing/shadow custom properties, Archivo font import) and
component classes, all **`md-`-prefixed** (`.md-btn`, `.md-btn-primary/
secondary/ghost`, `.md-card`, `.md-tag`, `.md-field`, `.md-input`) to avoid
colliding with Bootstrap or `site.css`'s existing overrides — `site.css`
already redefines `.btn-primary` site-wide as orange (`#ff6a00`); reusing the
mockup's own unprefixed `.btn-primary` would silently collide with that.
Bootstrap grid/utility classes (`.row`, `.col-*`, `.d-flex`, etc.) continue to
be used for layout.

## Homepage Page-Builder blocks

Extends the existing `Builder::make('content')->blocks([...])` array in
`app/Filament/Resources/PageResource.php` — no separate builder or resource.

| Block type | Status | Notes |
|---|---|---|
| `hero_banner` | **New** | `tag`, `heading`, `body`, `search_placeholder`, two CTAs (`cta_primary_label/url`, `cta_secondary_label/url`), `image`. Kept distinct from the existing `hero`/`hero_carousel` blocks (single CTA, no tag, no search bar, no side-image layout) rather than repurposing them, per the existing "additive, don't replace" convention — `hero`/`hero_carousel` remain valid, untouched block types for other pages. |
| `trust_badges` | **New** | `Repeater` of `{icon, label}`; `icon` is a `Select` from the fixed set of 4 icons the mockup uses (shield-check, package-check, handshake, message-square) — not free text, so rendering stays predictable. |
| `featured_categories` | **Reused, restyled** | No schema change. Blade view adds a live published-product count per selected category (direct products, or summed across descendants if the category is a hub) and a hardcoded "View all categories" link to `/products`. |
| `deals_banner` | **New** | `heading`, `body`, `cta_label`, `cta_url` — a plain editorial promo strip. No real "deal" flag/logic, since `Product` has no such column; this is intentionally just content, not tied to specific products. |
| `featured_products` | **Reused, restyled** | No schema change. No supplier line (the existing block never rendered one). Hardcoded "View all products" link to `/products`. Card shows product name, `price_display`, `quantity` (as MOQ), primary image. |
| `rfq_form_embed` | **Reused, restyled, schema extended** | Add two optional fields to the existing block: `tag` (short label, e.g. "Request for Quote") and `body` (intro copy). Still renders `partials.quote-request-form-fields.blade.php` completely unchanged — same fields, same validation, same `/quote-requests` submission. Only the wrapping markup/CSS changes, scoped to this block's container so its Bootstrap `form-control`/`form-select`/`btn` classes pick up the Modernist look without a global class override that would affect the same partial elsewhere (e.g. the product-detail-page quote modal). |
| `newsletter_signup` | **New** | `heading`, `subheading`. Renders a form posting to `POST /newsletter/subscribe`. |

The seeded home `Page` row's `content` array is updated to use the new block
set in the new order; the current `hero_carousel` and `content_strip` entries
are removed from the homepage specifically (both remain available as block
types for other pages/content editors to use elsewhere).

## Newsletter signup (new feature)

- Migration: `subscribers` table — `id`, `email` (unique), timestamps.
- `app/Models/Subscriber.php` — `fillable = ['email']`.
- `app/Http/Controllers/NewsletterController.php@store` — validates `email`
  (required, valid email), `firstOrCreate`s by email (re-submitting an email
  you've already subscribed with is a silent no-op, not a validation error),
  redirects back with a flash message (`session('newsletter_subscribed')`),
  following the same flash-message pattern already used for RFQ submissions.
- Route: `POST /newsletter/subscribe`, public, no auth guard — matches the
  RFQ form's no-account-required pattern.

## Testing

- `tests/Feature/NewsletterSubscriptionTest.php` — valid email creates a
  `Subscriber` row and redirects with the flash message; resubmitting the same
  email doesn't error or create a duplicate row; invalid/missing email is
  rejected with a validation error.
- `tests/Feature/HomepageBlocksTest.php` — seeds a `Page` with one of each new
  block type and asserts each renders its expected content (heading text,
  category/product names and counts, trust badge labels, RFQ form fields,
  newsletter form) without error.
- The existing `PageBlockRenderingTest`, `ContentStripBlockTest`, and
  `HeroCarouselBlockTest` must continue to pass unchanged — this phase must not
  break rendering for block types that remain in use on other pages.
