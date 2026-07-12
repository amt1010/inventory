# Product Catalog CMS + RFQ System — Design Spec

Date: 2026-07-12
Status: Approved (pending user's final review of this document)

## Purpose

Replace the current single-tenant inventory-admin app with a B2B product catalog site
styled after AFL's site (see reference PDFs: AFL SUB-CATEGORY PAGE, AFL PRODUCT PAGE,
AFL PRODUCT LISTING, AFL PRODUCT DETAIL PAGE, AFL CATEGORY PAGE; and the "Request a
Quote" form screenshot). No e-commerce/payments — buyers submit "Request a Quote" (RFQ)
enquiries instead of checking out.

Not a marketplace: no seller/vendor accounts. Three actors:
- **Buyers** — public visitors browsing the catalog and submitting quote requests.
  Accounts are optional (used only to view past requests and favorites).
- **Staff** — internal CMS users with one of three roles: Admin, Content Editor, Sales.
- **Admin** (subset of Staff) — full control including staff/user management.

## Architecture Overview

- Single codebase: existing Laravel 9 app, extended (not replaced).
- Public catalog + content pages: Blade + Bootstrap, server-rendered for SEO.
- CMS admin: new **Filament** panel mounted at `/admin`, using its own `staff` guard.
  Chosen over Laravel Backpack (paid Pro tier gaps, weaker RBAC) and a hand-built admin
  (too slow to build CRUD/RBAC/uploads from scratch — the trap the current app is in).
- Buyers: existing `users` table/guard, repurposed — no purchasing, just optional
  registration for request history and favorites.
- File storage: Laravel's local `public` disk (images, spec-sheet PDFs) — same as today,
  no S3 migration required for v1.
- Data rebuild: this is a clean rebuild, not a migration. There is no real production
  data in the current `product`/`menu`/`submenu`/`thirdmenu`/`lastmenu`/`employees`/
  `adminquery` tables, so they are dropped and replaced by proper versioned Laravel
  migrations (the current app has no migrations for its actual business tables at all —
  this also fixes that gap). The broken `Officer` model and `employees` guard/dashboard
  are retired along with them.

## Data Model

**`categories`** (self-referencing tree, replaces Topmenu/Submenu/Thirdmenu/Lastmenu)
- `id`, `parent_id` (nullable FK → categories), `name`, `slug` (unique per-parent, not
  global), `description` (rich text), `image` (hero banner), `status`
  (draft/published), `sort_order`, timestamps.
- One table handles every depth. A category with children renders as a hub (tiles to
  its children); a category with none renders its directly-attached products as a
  grid. This single structure covers every level seen in the AFL reference pages
  (Products hub → Category → Sub-Category → Product-Family).

**`products`**
- `id`, `category_id` (FK → categories, required, always a leaf), `name`, `slug`,
  `sku` (product number, searchable), `short_description`, `description` (rich text),
  `features` (JSON string list), `applications` (JSON string list), `spec_sheet_path`
  (nullable PDF), `status`, `sort_order`, timestamps.

**`product_images`**
- `id`, `product_id` (FK), `path`, `sort_order`, `is_primary`, timestamps.

**`quote_requests`** (the RFQ inbox, replaces `Adminquery`)
- `id`, `product_id` (nullable FK — null means general inquiry), `user_id` (nullable
  FK → users, set if buyer was logged in), `first_name`, `last_name`, `email`,
  `phone`, `company`, `country`, `market`, `city`, `state`, `message`,
  `contact_preference` (email/phone), `source_url`, `status`
  (new/in_progress/closed), `assigned_to` (nullable FK → staff), timestamps.
- `country`/`market` are simple selects backed by a static config list, not their
  own CMS-managed tables (avoids over-building for v1).

**`quote_request_notes`**
- `id`, `quote_request_id` (FK), `staff_id` (FK), `note`, `created_at` — internal
  Sales notes / audit trail.

**`favorites`**
- `id`, `user_id` (FK), `product_id` (FK), timestamps.

**`staff`** + Spatie `laravel-permission` roles/permissions tables
- `id`, `name`, `email`, `password`, timestamps. Roles: Admin, Content Editor, Sales.
- Replaces `Employee`/`Officer`.

**`users`** (buyers) — kept mostly as-is from the current app.

**`pages`** (block-based content pages — see Content Pages section)
- `id`, `title`, `slug`, `content` (JSON, Filament Builder field), `meta_title`,
  `meta_description`, `status` (draft/published), timestamps.

**`nav_items`**
- `id`, `label`, `url` (editor-entered path, e.g. `/about` or
  `/products/fiber-optic-cable`), `location` (header/footer), `parent_id`
  (nullable, one level of dropdown), `sort_order`.

Related products are **not** a stored relation — computed at render time as "other
products in the same category."

## Public Pages & Routing

**Catalog route**: `GET /products/{path?}`, a wildcard capturing all remaining slug
segments (e.g. `fiber-optic-cable/aerial/opgw/centracore-opgw-cable`) — fully nested,
SEO-friendly URLs matching AFL's pattern.

**Resolution** (`CatalogController@show`):
1. Split `{path}` into segments.
2. Walk the `categories` tree from root, matching each segment to a child's `slug`
   under the current parent, building the breadcrumb as we go.
3. If all segments resolve to categories → render the Category page template.
4. If the last segment matches a product slug within the current leaf category →
   render the Product Detail page.
5. No match at any step → 404.

**Two Blade templates cover the whole catalog:**
- `catalog/category.blade.php` — breadcrumb, hero banner + description, grid of
  children (sub-categories or, at leaf level, products), auto-related products.
  Reused recursively at every depth.
- `catalog/product.blade.php` — breadcrumb, image gallery, Features/Applications
  bullet lists, description, spec-sheet download, related products (same-category
  siblings), "Get a Quote" CTA opening the RFQ form pre-filled with this product.

**Content page route**: `GET /{slug}` (and `/` for the homepage) →
`PageController@show`, which loads a `pages` row and renders its `content` blocks in
order (see Content Pages section). Homepage is not special-cased — it's a `pages` row
with slug `home`.

**Search**: top search bar (`?q=`) implemented via **Laravel Scout** using its
built-in `database` driver for v1 (functionally a `LIKE` query against `name`/`sku`,
zero new infrastructure). Chosen specifically so it's a driver swap, not a rewrite,
if a dedicated engine is needed later — Scout has official drivers for Meilisearch
and Typesense, both open-source, self-hostable, and both support hybrid/vector
search natively, so semantic search can be added later without leaving Scout's API
or touching controllers/views.

## RFQ / Quote Request Workflow

**Form fields** (matches the reference screenshot): Reason for Contact (dropdown:
"Request a Quote" / "General Inquiry"), First Name, Last Name, Email, Phone,
Company, Country (static select), Market (static select), City, State, Message
(auto-includes product link/name when launched from a Product Detail page), Contact
Preference (Email/Phone radio), privacy-policy checkbox, Google reCAPTCHA v2
checkbox (requires site/secret key config).

No file-attachment upload in v1 (not in the reference screenshot; can be added later
as a nullable `attachment_path` column).

**Launch points**: (a) slide-in panel on any Product Detail page, pre-filled with
`product_id` + `source_url`; (b) embedded on the Contact-Us content page via the
"RFQ Form Embed" block, with `product_id` null.

**On submission**: validated → stored as a `quote_requests` row → email notification
sent to a configured Sales distribution list (Laravel `Mail`) → linked to `user_id`
if the buyer is logged in. The CMS record is the source of truth; email is a
notify-only side channel so a delivery failure doesn't lose the request.

**Sales dashboard** (Filament, Sales role): list view filterable by status/assigned
rep/date/product; detail view with all fields + internal notes thread
(`quote_request_notes`) + status change + reassignment; CSV export action.
A webhook integration to a specific external CRM is **not** built now (no target CRM
named) — the `quote_requests` table and CSV export are the integration point for
that later.

**Buyer side** (if registered): read-only "My Quote Requests" history page and a
"Favorites" list.

## CMS Admin (Filament) & RBAC

Panel mounted at `/admin`, own `staff` guard, roles via `spatie/laravel-permission`:

- **Admin** — full access to everything, plus staff user management (create/
  deactivate staff, assign roles).
- **Content Editor** — full CRUD on `categories`, `products`, `pages`, `nav_items`.
  No access to quote requests.
- **Sales** — full access to the Quote Requests dashboard only. Read-only visibility
  into Products (to reference specs when responding). No create/edit/delete there.

**Filament Resources**: `CategoryResource` (tree-aware form, parent picker, relation
manager for children), `ProductResource` (category picker, repeatable
Features/Applications inputs, image gallery relation manager with drag-reorder,
spec-sheet upload), `QuoteRequestResource` (Sales-only), `StaffResource`
(Admin-only), `PageResource` (Builder field for blocks), `NavItemResource`.

Each Resource has a Policy enforcing role access server-side (not just hidden nav) —
e.g. a Sales-role user cannot open the Product edit screen even via direct URL.

## Content Pages & Navigation

`pages` are built from reorderable content blocks (Filament's native `Builder` form
field, stored as JSON on `pages.content` — no separate `page_blocks` table needed).
Covers Home, About, Policies (Privacy/Terms), Contact-Us, Resources.

**Block types (v1 set)**, each rendered by a matching Blade partial:
1. **Hero** — heading, subheading, background image, optional CTA button
2. **Rich Text** — WYSIWYG body copy
3. **Featured Categories Grid** — editor picks N categories to showcase as tiles
4. **Featured Products Grid** — editor picks N products to showcase
5. **RFQ Form Embed** — inline general-inquiry quote form (powers Contact-Us)
6. **Resource List** — repeatable {title, description, file or link} (Resources page,
   distinct from per-product spec sheets)
7. **FAQ / Accordion** — repeatable {question, answer} pairs

`PageController@show($slug)` loads the page and dispatches each block in order to its
partial. Same controller handles `/` (home) and every other page slug.

**Navigation**: `nav_items` lets Content Editors point header/footer links at any
page, category, or product by entering its path directly — deliberately simple
(raw URL string) rather than polymorphic linking, so no new code is needed to link
to new content.

## Error Handling

- Public catalog: unresolved category/product paths → standard 404 page.
- RFQ form: server-side validation with inline errors; reCAPTCHA failure blocks
  submission with a clear message; submission failures never silently lose data
  (CMS record first, email second).
- CMS: Filament's built-in validation + policy-based 403s for out-of-role access.

## Testing

Laravel feature tests for the two load-bearing flows:
1. Category-tree path resolution — root/nested/leaf/product-at-end/404 cases.
2. RFQ submission — valid submission creates a `quote_requests` row and queues the
   notification email; invalid submission re-renders with errors.

CMS CRUD screens rely on Filament's own tested framework behavior rather than custom
tests per resource.

## Out of Scope

- Payments/checkout, seller/vendor accounts (dropped from the earlier marketplace
  framing entirely).
- Multi-language/region support (single locale for v1).
- File-attachment uploads on the RFQ form.
- Webhook integration to a specific external CRM (no CRM named yet).
- Vector/AI-powered search (the Scout abstraction keeps this cheap to add later,
  but it is not built now).
- Data migration from the current app's tables (clean rebuild — no real data to
  preserve).

## Open Items for Follow-on Specs

- None currently blocking; this spec is believed complete for its stated scope.
