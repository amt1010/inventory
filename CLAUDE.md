# CLAUDE.md

This file gives Claude Code (and any other engineer) the context needed to work in
this repository without re-deriving it from scratch.

## What this project is

A B2B marketplace platform (Alibaba-style), styled after AFL's public catalog site.
Three actor types:

- **Buyers** — public visitors who browse the catalog and submit "Request a Quote"
  (RFQ) enquiries. No checkout, no payments anywhere in this system.
- **Sellers** — registered suppliers who list their own products/surplus inventory
  via the `/seller` Filament panel (built in a later plan). Never see buyer contact
  details or interact with buyers directly.
- **Staff** (Admin / Content Editor / Sales) — manage the catalog, price and approve
  seller listings, and handle quote requests via the `/admin` Filament panel.

This is an India-based business (GST numbers, INR pricing per the design spec);
see `APP_TIMEZONE` below.

Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md` —
read that before making architectural changes. Implementation plans (this codebase
is being built phase by phase) live in `docs/superpowers/plans/`.

## Tech stack

- Laravel 11, PHP 8.2, MySQL (dev/production; tests use SQLite — see below)
- Filament v3 for the internal CMS — two panels: `/admin` (staff guard, built) and
  `/seller` (seller guard, built in a later phase)
- `spatie/laravel-permission` for role-based access control (roles: `admin`,
  `content_editor`, `sales`, all on the `staff` guard)
- Laravel Scout (`database` driver) for catalog search — deliberately abstracted so
  swapping to Meilisearch/Typesense later is a driver change, not a rewrite
- Blade + Bootstrap (via CDN) for the public-facing catalog — no SPA framework

## Architecture map

- `app/Models/Category.php` — self-referencing tree (`parent_id`), any depth. A
  category with children renders as a hub; one without renders its products.
- `app/Models/Product.php` — belongs to exactly one `Seller` and one leaf
  `Category`. `status` moves through `pending_review → published` (or
  `rejected`/`archived`). `price_display` is a free-text field settable only by the
  Admin role — see `App\Policies\ProductPolicy::setPrice()`. Never set
  `status = 'published'` directly; call `Product::publish()`, which enforces that
  `price_display` is set first.
- `app/Http/Controllers/CatalogController.php` — resolves the wildcard route
  `/products/{path?}` by walking the category tree segment by segment; renders
  either the category template or the product template. This single controller and
  its two templates cover every depth of the catalog (Products hub, Category,
  Sub-Category, Product-Family, etc.) — there is deliberately no per-depth template.
- `app/Filament/Resources/` — staff-facing CRUD screens. Every resource has a
  matching `App\Policies\*Policy` enforcing role boundaries server-side (not just
  hidden nav items).
- Seller identity is never rendered on any public page — the catalog is fully
  platform-branded.

## Local development workflow

Prerequisites:

- PHP 8.2 with the `intl` and `zip` extensions enabled (required by
  `filament/support` and a Filament transitive dependency respectively). On XAMPP/
  Windows these ship as DLLs but are commented out by default — uncomment
  `extension=intl` and `extension=zip` in `php.ini` and confirm with `php -m`.
- MySQL, for the dev database.

First-time setup:

```
composer install
npm install
cp .env.example .env   # if .env doesn't already exist
php artisan key:generate
```

If `composer install` fails with an error about a security-advisory policy
blocking `laravel/framework`: Composer 2.10+ refuses by default to install any
package flagged by a security advisory, even one that's only relevant in debug
mode (this project currently pins `laravel/framework` to a range with 3 known,
accepted advisories — see "Known issues" below). Run
`composer config --global policy.advisories.block false` and retry. This is a
one-time, per-machine Composer setting, not something the project can pin in
`composer.json`.

Configure `.env` for your local MySQL instance (`DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`). Leave `APP_TIMEZONE=Asia/Kolkata` as set in `.env.example` — this
is an India-based business and pricing/GST features assume IST, not UTC. Then:

```
php artisan migrate:fresh --seed
```

This seeds the three staff roles, a login-ready admin account
(`admin@example.com` / `password`, created via `StaffSeeder` with the `admin`
role — change or remove before any real deployment), and sample catalog data.

Day-to-day commands:

- `php artisan serve` — run the app locally
- `php artisan test` — run the full test suite (do this before every commit).
  Tests run against an in-memory SQLite database, not your dev MySQL database
  (`phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` for the
  `testing` environment). This is deliberate so the suite never touches or wipes
  your seeded dev data — don't "fix" it back to MySQL.
- `php artisan test --filter=SomeTestName` — run a single test while iterating
- `php artisan tinker` — inspect data interactively
- `php artisan migrate:fresh --seed` — reset the local (MySQL) DB to a known state
- `/admin` — staff CMS (Admin / Content Editor / Sales)
- `/seller` — seller portal (added in a later phase, not yet built)
- `/products` — public catalog root
- `/search?q=...` — catalog search

### Known issues

`composer audit` reports 3 pre-existing `laravel/framework` advisories at the
currently pinned version (signed-URL path confusion, CRLF injection in email
validation). These are known and accepted for now — they're most relevant when
`APP_DEBUG=true`, so make sure `APP_DEBUG=false` in any real deployment.

## Conventions and best practices for working in this codebase

- **Test-first.** Every new behavior gets a failing test before the implementation.
  Feature tests live in `tests/Feature`.
- **RBAC lives in Policies, not just Filament form visibility.** Any field or action
  that must be role-gated needs both a Policy method (the actual authorization
  boundary) and, in Filament, `->disabled()` **and** `->dehydrated()` tied to that
  same policy check — `disabled()` alone is cosmetic and can be bypassed.
- **Categories are one self-referencing table, not fixed named levels.** Never
  reintroduce hardcoded category-depth tables (the legacy app's
  `Topmenu`/`Submenu`/`Thirdmenu`/`Lastmenu` pattern) — that's exactly what this
  rebuild replaced.
- **No payment/checkout code, ever, per the spec.** Final pricing is negotiated
  off-platform after the RFQ conversation. If a task seems to need a payment
  gateway, stop and re-check the spec — it almost certainly means the requirement
  was misread.
- **Seller identity stays internal.** Never add seller name/company to a
  public-facing view or API response — `products.seller_id` is for internal use
  (Admin/Sales, and the seller's own portal) only.
- **A product cannot be `published` without `price_display` set** — this is
  enforced in `Product::publish()`, not re-implemented ad hoc elsewhere.
- Commit frequently, in small units — one logical change per commit, tests passing
  at each commit.
