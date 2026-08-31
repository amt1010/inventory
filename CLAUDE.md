# CLAUDE.md

This file gives Claude Code (and any other engineer) the context needed to work in
this repository without re-deriving it from scratch.

@SESSION-LOGGING.md

## What this project is

A B2B marketplace platform (Alibaba-style), styled after AFL's public catalog site.
Three actor types:

- **Buyers** — public visitors who browse the catalog and submit "Request a Quote"
  (RFQ) enquiries. No checkout, no payments anywhere in this system. An account
  (`web` guard, the stock `users` table) is optional and low-friction — no email
  verification — used only to view past quote requests and favorites. Buyers who
  registered with email/password can self-service reset it at `/forgot-password`;
  accounts that signed up via Clerk/Google with no local password (`password IS NULL`)
  are not eligible — same rule applies to sellers.
- **Sellers** — registered suppliers who list their own products/surplus inventory
  via the `/seller` Filament panel. Never see buyer contact details or interact
  with buyers directly.
- **Staff** (Admin / Content Editor / Sales) — manage the catalog, price and approve
  seller listings, manage content pages/navigation, and handle quote requests via
  the `/admin` Filament panel.

This is an India-based business (GST numbers, INR pricing per the design spec);
see `APP_TIMEZONE` below.

Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md` —
read that before making architectural changes. Implementation plans (this codebase
is being built phase by phase) live in `docs/superpowers/plans/`.

## Tech stack

- Laravel 11, PHP 8.2, MySQL (dev/production; tests use SQLite — see below)
- Filament v3 for the internal CMS — two panels: `/admin` (staff guard) and
  `/seller` (seller guard)
- `spatie/laravel-permission` for role-based access control (roles: `admin`,
  `content_editor`, `sales`, all on the `staff` guard). Buyer-facing features
  (favorites, quote-request history) use plain `where('user_id', ...)` query
  scoping instead — no Policy/Gate class, since there's no Filament panel involved.
- Laravel Scout (`database` driver) for catalog search — deliberately abstracted so
  swapping to Meilisearch/Typesense later is a driver change, not a rewrite
- Blade + Bootstrap (via CDN) for the public-facing catalog — no SPA framework

## Architecture map

- `app/Models/Category.php` — self-referencing tree (`parent_id`), any depth. A
  category with children renders as a hub; one without renders its products.
  Sellers may propose a new leaf category inline from the product form (a
  Filament create-option combo box on `category_id`); the proposal lands as an
  ordinary `status = 'draft'` category tagged with `proposed_by_seller_id` —
  invisible to buyers until Admin reviews, optionally corrects (name, slug,
  parent), and publishes it via the existing `/admin/categories` screen, at
  which point the associated product's own review can proceed to
  `Product::publish()` (which now also requires the category to be published).
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
- `app/Models/Page.php` + `app/Http/Controllers/PageController.php` — block-based
  content pages (`resources/views/blocks/*.blade.php`), resolved by the catch-all
  `/{slug}` route (registered last in `routes/web.php`, after every other route,
  so it never shadows `/products/{path?}`, `/search`, etc.).
- `app/Models/NavItem.php` — self-referencing header/footer nav with a one-level
  nesting cap enforced both in the Filament form and server-side in
  `NavItemResource`'s `rule()`. Rendered globally via a view composer on
  `layouts.app` in `AppServiceProvider::boot()`.
- `app/Http/Controllers/{RegistrationController,SessionController,FavoriteController,
  QuoteRequestHistoryController}.php` — buyer-account features on the plain `web`
  guard. No new guard, no Policy layer; ownership is enforced by scoping every
  query to `auth('web')->id()`.

## Local development workflow

Prerequisites:

- PHP 8.2 with the `intl`, `zip`, and `gd` extensions enabled (required by
  `filament/support`, a Filament transitive dependency, and image-upload
  handling — product/document `FileUpload` fields and any test using
  `UploadedFile::fake()->image()` — respectively). On XAMPP/Windows these ship
  as DLLs but are commented out by default — uncomment `extension=intl`,
  `extension=zip`, and `extension=gd` in `php.ini` and confirm with `php -m`.
- MySQL, for the dev database.

First-time setup:

```
composer install
npm install
cp .env.example .env   # if .env doesn't already exist
php artisan key:generate
```

Product images and spec-sheet PDFs (`ProductImage`/`FileUpload` fields) are written
to the `public` disk, which is `public/storage` directly (see `config/filesystems.php`)
— not the usual Laravel `storage/app/public` + `storage:link` symlink. That symlink
approach broke in production: Railway's Pre-Deploy Command runs in a throwaway
container that's discarded before the container serving traffic boots, so a symlink
created there never reaches the container clients actually hit (see `DEPLOYMENT.md`'s
troubleshooting section). Pointing the disk at `public/storage` directly sidesteps
this — no symlink, no separate command, and it also avoids `storage:link` needing
elevated privileges to create symlinks on Windows. Laravel creates the directory
automatically on first upload; nothing to run by hand.

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
- `php artisan migrate` — apply new migrations only; never touches or drops
  existing data. **Use this — not `migrate:fresh` — to verify a new migration
  applies cleanly to the dev database**, including in any agentic
  implementation-plan "verification" step. This database holds real data
  entered through the actual app (product images, seller/buyer accounts) —
  treat it like production, not disposable scratch space.
- `php artisan migrate:fresh --seed` — ⚠️ **DESTROYS ALL DATA** in the local
  MySQL database and reloads only the dummy seed data. This is a one-way
  reset for when you deliberately want to throw away everything and start
  over from a known fixture state — it is **not** a routine "make sure the
  DB is up to date" command and must never be run once real data exists in
  it (uploaded product images, registered sellers/buyers, anything entered
  through `/admin`, `/seller`, or the public site). If you're unsure whether
  the current dev DB has real data worth keeping, ask before running this.
- `/admin` — staff CMS (Admin / Content Editor / Sales)
- `/seller` — seller portal
- `/products` — public catalog root
- `/search?q=...` — catalog search
- `/register`, `/login`, `/logout` — optional buyer accounts (`web` guard)
- `/favorites`, `/my-quote-requests` — buyer-only, require login
- `/{slug}` — CMS-managed content pages (catch-all, e.g. `/` resolves `slug=home`)

### Testing queued mail locally (optional)

Production uses Redis for cache/session/queue (see `DEPLOYMENT.md`), so all
outbound emails (seller activation/approval/rejection, product-listing
notifications, RFQ notifications) are queued rather than sent inline. Local
`.env` keeps `QUEUE_CONNECTION=sync` (and `CACHE_STORE=database`,
`SESSION_DRIVER=file`) by default, so day-to-day work needs no local Redis —
a queued mailable still executes immediately within the request, same as
`phpunit.xml`'s test environment.

To manually verify the real queued/worker behavior (e.g. before a release
that touches a `Mailable`), you need a local Redis-protocol-compatible
service — this project deliberately has no Docker setup, so:

- **Memurai Developer** (free, native Windows service, Redis-protocol
  compatible, listens on `127.0.0.1:6379` matching the `.env.example`
  placeholders) — closest fit to this project's native-Windows-tooling
  approach.
- Or, if you already use WSL2, `sudo apt install redis-server` there.

Then, in your local `.env` (not `.env.example`), temporarily set
`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
`REDIS_CLIENT=predis`; run `php artisan queue:work` in a second terminal;
trigger the flow you want to check (submit an RFQ, approve a seller, etc.)
and watch it execute in the worker terminal instead of inline. Revert
`.env` back to the `sync`/`database`/`file` defaults afterward.

### Clerk Google sign-in (optional locally)

Buyer register/login and seller register/panel-login have an optional
"Continue with Google" path via Clerk (see
`docs/superpowers/specs/2026-08-30-clerk-google-auth-design.md`). It's
off by default — every Clerk button and script tag is gated on
`CLERK_PUBLISHABLE_KEY` being set, so an empty `.env` behaves exactly
like before this feature existed.

To exercise it locally: create (or reuse) a Clerk application at
https://dashboard.clerk.com, enable the Google OAuth connection, and add
your local URL (e.g. `http://localhost:8000/auth/clerk/complete`) to its
allowed redirect URLs. Then set in `.env`:

```
CLERK_PUBLISHABLE_KEY=pk_test_...
CLERK_SECRET_KEY=sk_test_...
CLERK_FRONTEND_API=your-app-name.clerk.accounts.dev
```

`CLERK_FRONTEND_API` is the bare host shown on the dashboard's API Keys
page — no `https://` prefix. Restart `php artisan serve` after changing
it (config is cached per-request, not per-file-change, but a fresh
process picks up `.env` cleanly either way).

### Known issues

`composer audit` reports 3 pre-existing `laravel/framework` advisories at the
currently pinned version (signed-URL path confusion, CRLF injection in email
validation, and reflected XSS in the debug-mode error page). These are known
and accepted for now — they're most relevant when `APP_DEBUG=true`, so make
sure `APP_DEBUG=false` in any real deployment.

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
- **Buyer-facing ownership checks are plain query scoping, not Policies.**
  Favorites and quote-request history use `where('user_id', auth('web')->id())`
  directly in the controller. Don't introduce a Policy/Gate class for these —
  there's no Filament panel involved, so the Policy pattern used for staff RBAC
  doesn't apply.
- Commit frequently, in small units — one logical change per commit, tests passing
  at each commit.
