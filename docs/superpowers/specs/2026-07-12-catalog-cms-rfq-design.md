# B2B Marketplace CMS (AFL-style Catalog + RFQ) — Design Spec

Date: 2026-07-12
Status: Approved (pending user's final review of this document)

## Purpose

Replace the current single-tenant inventory-admin app with an Alibaba-style B2B
marketplace: Sellers list products/surplus inventory, Admin prices and publishes
listings, Buyers browse a catalog styled after AFL's site (see reference PDFs: AFL
SUB-CATEGORY PAGE, AFL PRODUCT PAGE, AFL PRODUCT LISTING, AFL PRODUCT DETAIL PAGE,
AFL CATEGORY PAGE; and the "Request a Quote" form screenshot) and submit "Request a
Quote" (RFQ) enquiries. No e-commerce/payments — no checkout, no payment gateway.
Final price is negotiated during the quote conversation, off the back of an
Admin-set approximate price range shown publicly.

Four actors:
- **Buyers** — public visitors browsing the catalog and submitting quote requests.
  Accounts are optional (used only to view past requests and favorites).
- **Sellers** — registered (self-service or Admin-created) suppliers who list their
  own products/inventory. Never interact with buyers directly and are not shown
  publicly — see "Seller Visibility" below.
- **Staff** — internal CMS users with one of three roles: Admin, Content Editor,
  Sales.
- **Admin** (subset of Staff) — full control: approves sellers, prices and approves
  product listings, manages staff/users, plus everything Content Editor and Sales
  can do.

## Architecture Overview

- Single codebase: existing Laravel 9 app, extended (not replaced).
- Public catalog + content pages: Blade + Bootstrap, server-rendered for SEO.
- CMS admin: new **Filament** install with **two panels**:
  - `/admin` — `staff` guard, used by Admin/Content Editor/Sales.
  - `/seller` — `seller` guard, used by Sellers, scoped entirely to their own
    records.
  Filament natively supports multiple panels in one app, so the Seller Portal is
  additive rather than a separate subsystem. Filament chosen over Laravel Backpack
  (paid Pro tier gaps, weaker RBAC) and a hand-built admin (too slow to build
  CRUD/RBAC/uploads from scratch — the trap the current app is in).
- Buyers: existing `users` table/guard, repurposed — no purchasing, just optional
  registration for request history and favorites.
- File storage: Laravel's local `public` disk (images, spec-sheet PDFs, seller
  documents) — same as today, no S3 migration required for v1.
- Data rebuild: this is a clean rebuild, not a migration. There is no real
  production data in the current `product`/`menu`/`submenu`/`thirdmenu`/`lastmenu`/
  `employees`/`adminquery` tables, so they are dropped and replaced by proper
  versioned Laravel migrations (the current app has no migrations for its actual
  business tables at all — this also fixes that gap). The broken `Officer` model
  and `employees` guard/dashboard are retired along with them.

## Data Model

**`categories`** (self-referencing tree, replaces Topmenu/Submenu/Thirdmenu/Lastmenu)
- `id`, `parent_id` (nullable FK → categories), `name`, `slug` (unique per-parent, not
  global), `description` (rich text), `image` (hero banner), `status`
  (draft/published), `sort_order`, timestamps.
- One table handles every depth. A category with children renders as a hub (tiles to
  its children); a category with none renders its directly-attached products as a
  grid. This single structure covers every level seen in the AFL reference pages
  (Products hub → Category → Sub-Category → Product-Family).
- Managed by Admin/Content Editor only — Sellers pick from this existing tree when
  listing a product; they do not create categories.

**`sellers`**
- `id`, `company_name`, `contact_person`, `phone`, `email` (unique, login),
  `password`, `business_address`, `gst_number`, `status`
  (`pending_email_verification` / `pending_admin_approval` / `approved` /
  `rejected` / `suspended`), `created_by` (`self` / `admin`), `rejection_reason`
  (nullable), `email_verified_at` (nullable), `approved_at` (nullable),
  `approved_by` (nullable FK → staff), timestamps.

**`seller_documents`**
- `id`, `seller_id` (FK), `label`, `file_path`, `uploaded_at` — supporting business
  documents (e.g. GST certificate, trade license); multiple allowed per seller.

**`custom_attributes`** (polymorphic — ad hoc extra fields on a Seller or a Product)
- `id`, `attributable_type`, `attributable_id`, `label`, `value` (nullable text),
  `file_path` (nullable), `sort_order`, timestamps.
- Lets Admin bolt on fields that weren't anticipated at build time (e.g. "Fiber
  Count: 96" on one product, "Import License: [file]" on one seller) without a code
  change. Deliberately per-record, not a reusable schema — see the "Custom Fields"
  decision in the brainstorming history for the simpler-vs-structured trade-off.

**`products`**
- `id`, `seller_id` (FK → sellers, required — every product belongs to exactly one
  seller/inventory owner), `category_id` (FK → categories, required, always a
  leaf), `name`, `slug`, `sku` (product number, searchable), `short_description`,
  `description` (rich text), `features` (JSON string list), `applications` (JSON
  string list), `spec_sheet_path` (nullable PDF), `price_display` (free-text
  string, nullable — e.g. "₹1,200 – ₹1,800 per reel"; INR only, no multi-currency
  in v1), `status` (`pending_review` / `published` / `rejected` / `archived`),
  `sort_order`, timestamps.
- `price_display` is set by Admin only, never by the Seller (see Product Listing &
  Pricing Workflow). A product cannot be `published` without it.

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
- Sellers never get access to this table — see RFQ Workflow.

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

## Seller Onboarding Workflow

- **Self-registration**: Seller fills a public registration form (company name,
  contact person, phone, email, business address, GST number, document upload) →
  status `pending_email_verification` → activation email sent → seller clicks link
  → status becomes `pending_admin_approval` → Admin reviews in the CMS (profile +
  uploaded docs) → **Approve** (status → `approved`, seller can log in and list
  products) or **Reject** (status → `rejected`, with a reason the seller can see).
- **Admin-initiated**: Admin creates the seller record directly in the CMS → status
  `pending_email_verification`, flagged `created_by=admin` → activation email sent
  (seller sets their password) → on activation, status goes straight to `approved`
  (no separate approval click — Admin creating the account already **is** the
  vetting).
- Only `approved` sellers can log in to the `/seller` panel and submit listings.

## Product Listing & Pricing Workflow

1. An approved Seller logs into the Seller Portal (`/seller`) and submits a product
   (name, category picked from the existing Admin-curated tree, description,
   features/applications, images, quantity — **no price field available to
   sellers**). Status → `pending_review`, not visible on the public catalog.
2. Admin reviews in `/admin`: can edit/clean up content, sets `price_display`, and
   either **Publishes** (status → `published`, now live on the public catalog) or
   **Rejects** (with a reason visible to the seller).
3. Content Editor can still edit content fields (description, images, category) on
   already-published products for cleanup/quality control, but **cannot** set price
   or approve pending listings — that stays Admin-only.
4. A Seller can edit their own listing at any time; editing a `published` listing
   reverts its status to `pending_review` (re-approval needed), so a seller edit
   can't silently invalidate Admin's pricing decision.
5. Admin can also create/edit a product directly through `/admin` on behalf of a
   seller, using the same `ProductResource`.

## Public Pages & Routing

**Catalog route**: `GET /products/{path?}`, a wildcard capturing all remaining slug
segments (e.g. `fiber-optic-cable/aerial/opgw/centracore-opgw-cable`) — fully nested,
SEO-friendly URLs matching AFL's pattern.

**Resolution** (`CatalogController@show`):
1. Split `{path}` into segments.
2. Walk the `categories` tree from root, matching each segment to a child's `slug`
   under the current parent, building the breadcrumb as we go.
3. If all segments resolve to categories → render the Category page template.
4. If the last segment matches a **published** product slug within the current leaf
   category → render the Product Detail page.
5. No match at any step → 404 (this includes `pending_review`/`rejected`/`archived`
   products — never resolvable on the public site).

**Two Blade templates cover the whole catalog:**
- `catalog/category.blade.php` — breadcrumb, hero banner + description, grid of
  children (sub-categories or, at leaf level, published products), auto-related
  products. Reused recursively at every depth.
- `catalog/product.blade.php` — breadcrumb, image gallery, Features/Applications
  bullet lists, description, `price_display` (approximate range), spec-sheet
  download, related products (same-category siblings), "Get a Quote" CTA opening
  the RFQ form pre-filled with this product.
- **Seller identity is never shown** on any public page — the platform appears as a
  single anonymous, platform-branded catalog. Internally, Admin/Sales always know
  which seller owns a product via `products.seller_id`.

**Content page route**: `GET /{slug}` (and `/` for the homepage) →
`PageController@show`, which loads a `pages` row and renders its `content` blocks in
order (see Content Pages section). Homepage is not special-cased — it's a `pages` row
with slug `home`.

**Search**: top search bar (`?q=`) implemented via **Laravel Scout** using its
built-in `database` driver for v1 (functionally a `LIKE` query against `name`/`sku`,
scoped to `published` products only, zero new infrastructure). Chosen specifically so
it's a driver swap, not a rewrite, if a dedicated engine is needed later — Scout has
official drivers for Meilisearch and Typesense, both open-source, self-hostable, and
both support hybrid/vector search natively, so semantic search can be added later
without leaving Scout's API or touching controllers/views.

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

**Admin/Sales are the sole respondents.** Sellers never see individual quote
requests or buyer contact details — final pricing is negotiated by Admin/Sales
directly with the buyer (coordinating with the seller off-platform as needed). A
Seller's only visibility into this activity is an **aggregate quote-request count
per product** (a simple `COUNT(*)`, exposed as a read-only widget/column in the
Seller Portal — not access to the `quote_requests` table).

**Sales dashboard** (Filament, `/admin`, Sales role): list view filterable by
status/assigned rep/date/product; detail view with all fields + internal notes
thread (`quote_request_notes`) + status change + reassignment; CSV export action.
A webhook integration to a specific external CRM is **not** built now (no target CRM
named) — the `quote_requests` table and CSV export are the integration point for
that later.

**Buyer side** (if registered): read-only "My Quote Requests" history page and a
"Favorites" list.

## CMS Admin (Filament) & RBAC

**`/admin` panel** — `staff` guard, roles via `spatie/laravel-permission`:
- **Admin** — full access to everything: seller approval, product pricing/approval,
  categories, pages, nav, quote requests, plus staff user management (create/
  deactivate staff, assign roles).
- **Content Editor** — full CRUD on `categories`, `pages`, `nav_items`, and content
  fields on `products` (not price, not approval — see Product Listing & Pricing
  Workflow). No access to quote requests or seller approval.
- **Sales** — full access to the Quote Requests dashboard only. Read-only
  visibility into Products (to reference specs when responding). No create/edit/
  delete there, no seller access.

**`/seller` panel** — `seller` guard, entirely scoped to the logged-in seller's own
records: their product listings (create/edit per the workflow above, price field
absent/read-only), quote-request count per product, their profile/business info +
documents, custom attributes on their own products.

**Filament Resources** (`/admin`): `CategoryResource` (tree-aware form, parent
picker, relation manager for children), `ProductResource` (seller/category picker,
repeatable Features/Applications inputs, image gallery relation manager with
drag-reorder, spec-sheet upload, price field, approve/reject actions),
`SellerResource` (approve/reject actions, document review), `QuoteRequestResource`
(Sales-only), `StaffResource` (Admin-only), `PageResource` (Builder field for
blocks), `NavItemResource`.

Each Resource has a Policy enforcing role access server-side (not just hidden nav) —
e.g. a Sales-role staff account cannot open the Product editor even via direct URL,
and a Seller can never query another seller's products or any `quote_requests` row.

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

- Public catalog: unresolved category/product paths, and any non-`published`
  product, → standard 404 page.
- RFQ form: server-side validation with inline errors; reCAPTCHA failure blocks
  submission with a clear message; submission failures never silently lose data
  (CMS record first, email second).
- Seller registration: server-side validation on all profile fields; duplicate
  email blocked with a clear message.
- CMS: Filament's built-in validation + policy-based 403s for out-of-role/
  out-of-scope access (staff role boundaries, and a seller attempting to access
  another seller's records or any buyer/quote data).

## Testing

Laravel feature tests for the load-bearing flows:
1. Category-tree path resolution — root/nested/leaf/product-at-end/404 cases.
2. RFQ submission — valid submission creates a `quote_requests` row and queues the
   notification email; invalid submission re-renders with errors.
3. Seller onboarding — self-registration reaches `pending_admin_approval` only
   after email activation; Admin-created seller reaches `approved` after
   activation alone; rejected sellers cannot log in to `/seller`.
4. Product listing workflow — a `pending_review` product never resolves on the
   public catalog route; publishing requires `price_display` to be set; editing a
   published product reverts it to `pending_review`.
5. Access scoping — a Seller cannot read another seller's products, and cannot
   query `quote_requests` at all; a Content Editor cannot set price or approve a
   listing; a Sales-role account cannot open the Product editor.

CMS CRUD screens otherwise rely on Filament's own tested framework behavior rather
than custom tests per resource.

## Out of Scope

- Payments/checkout (final price is agreed off-platform after the RFQ conversation;
  no payment gateway anywhere in this system).
- Seller-buyer direct messaging (Admin/Sales are always the intermediary).
- Public seller identity/storefronts (fully anonymous, platform-branded catalog).
- Multi-currency (INR only in v1).
- Multi-language/region support (single locale for v1).
- File-attachment uploads on the RFQ form.
- Webhook integration to a specific external CRM (no CRM named yet).
- Vector/AI-powered search (the Scout abstraction keeps this cheap to add later,
  but it is not built now).
- Reusable/structured custom fields (v1 uses simple per-record ad hoc attributes,
  not an Admin-defined field schema — see Data Model).
- Data migration from the current app's tables (clean rebuild — no real data to
  preserve).

## Open Items for Follow-on Specs

- None currently blocking; this spec is believed complete for its stated scope.
