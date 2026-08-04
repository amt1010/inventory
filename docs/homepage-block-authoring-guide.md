# Homepage Block Authoring Guide

For Content Editors and Admins managing the public Homepage via `/admin →
Pages → Home`. Covers the seven block types used on the new "Modernist"
homepage design (shipped 2026-08-04). These are ordinary Page Builder blocks —
the same mechanism used for every other content page — so everything here
also applies if you add these blocks to other pages.

## How it works

1. Go to `/admin` → **Pages** → **Home** → Edit.
2. The **Content** field is a stack of blocks. Drag the handle on the left of
   a block to reorder it; use the trash icon to delete one, or **Add block**
   at the bottom to insert a new one anywhere in the stack.
3. Click a block's header to expand/collapse its fields.
4. **Save** applies changes immediately — there's no build step, cache to
   clear, or deploy needed. Refresh the public homepage to see the result.
5. The page itself has a **Status** field (Draft/Published) — it must be
   "Published" for any of this to be visible to buyers at all, independent of
   the individual blocks' content.

## Block reference

### Hero Banner (Modernist)

The large banner at the top of the page.

| Field | Required | Notes |
|---|---|---|
| Tag | No | Small label above the heading, e.g. "B2B Sourcing Marketplace". Leave blank to hide it. |
| Heading | **Yes** | The big headline. |
| Body | No | One paragraph under the heading. |
| Search placeholder | No | Placeholder text in the hero's search box. This search box is fully functional — it submits to the real catalog search, same as the header search bar. Leave blank to hide the search box entirely. |
| Primary CTA label / URL | No | The solid button, e.g. "Browse Products" → `/products`. Both fields must be filled for the button to show. |
| Secondary CTA label / URL | No | The outlined button, e.g. "Request a Quote" → `/#rfq` (jumps to the RFQ block further down the page, if `rfq` is that block's anchor — see the RFQ Form Embed note below). |
| Image | No | Shown on the right side of the hero. |

### Trust Badges

The row of small icon + label callouts (e.g. "Verified Suppliers").

Add one row per badge via **Add item**. Each row has:
- **Icon** — a fixed dropdown of 4 icons (Shield Check, Package Check,
  Handshake, Message Square). You can't upload a custom icon here — it's
  deliberately a closed set so the page can't end up with a broken/missing
  icon.
- **Label** — the text next to the icon.

### Shop by Category (`featured_categories` block)

| Field | Required | Notes |
|---|---|---|
| Heading | No | |
| Categories | **Yes** | Multi-select, searchable. Only **published** categories are selectable, and only published categories will actually render even if selected earlier and later unpublished. |

The product count under each category name ("N products") is **computed
automatically** from the live catalog — it counts published products in that
category and all its sub-categories. There's no field to type a count in; you
can't get it out of sync with reality. The "View all categories" link is also
automatic and always points to `/products`.

Cards never show a supplier/company name — that's intentional, not a missing
field. Seller identity is never shown on any public page.

### Deals Banner

The full-width red promotional strip (e.g. "Bulk Deals This Week").

| Field | Notes |
|---|---|
| Heading | Defaults to "Bulk Deals This Week" |
| Body | One line of supporting copy |
| CTA label / URL | The button, e.g. "Shop Deals" → `/products` |

This is plain editorial content — there's no actual "deal" flag on products,
so this block doesn't filter or highlight specific products automatically.
Point the CTA URL wherever makes sense (e.g. a specific category).

### Featured Products (`featured_products` block)

| Field | Required | Notes |
|---|---|---|
| Heading | No | |
| Products | **Yes** | Multi-select, searchable. Only **published** products are selectable/shown. |

Each card automatically shows the product's price (`price_display`, the
Admin-set price field on the product itself), quantity as "MOQ: N", and its
primary image — none of that is editable from this block, it's pulled live
from the product record. Same rule as categories: no supplier name is ever
shown. "View all products" always points to `/products`.

### RFQ Form Embed

The "Can't find exactly what you need?" section.

| Field | Notes |
|---|---|
| Tag | Small label above the heading, e.g. "Request for Quote" |
| Heading | Defaults to "Request a Quote" |
| Body | Intro copy above the form |

**The form fields themselves are not editable here.** This block always
embeds the real, full Request-a-Quote form (name, email, phone, reason,
privacy checkbox, etc.) — the same form used everywhere else on the site,
submitting to the same place. This is deliberate: it's the one real RFQ
pipeline, not a second lightweight one, so every quote request behaves
identically regardless of which page it came from.

### Newsletter Signup

| Field | Notes |
|---|---|
| Heading | Defaults to "Get sourcing updates & deals" |
| Subheading | One line under the heading |

The email field and Subscribe button are fixed — only the surrounding copy is
editable. Submissions go to a `subscribers` table (visible via `php artisan
tinker`; there's no admin screen for the list yet).

## Things you can't do from `/admin` (by design)

- Show a seller/supplier/company name on any card. This isn't a missing
  field — it's a hard rule for this platform (seller identity stays
  internal).
- Flag a product as "on deal" to auto-populate the Deals Banner or add a
  "Deal" tag to a product card — no such field exists on products yet.
- Change the RFQ form's fields, or point it at a different endpoint.
- Edit the mega-menu category dropdown from a Page block — that's driven
  directly by the live category tree, not page content (see **Categories**
  in `/admin`).
