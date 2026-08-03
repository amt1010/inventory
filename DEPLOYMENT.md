# Deploying to Railway

This app is deployed to [Railway](https://railway.com), which runs Laravel
natively via Nixpacks (auto-detected PHP-FPM + Caddy — no Dockerfile,
`Procfile`, or `nixpacks.toml` needed in this repo).

Everything in this file is a one-time setup you do in Railway's dashboard —
it needs your Railway account and GitHub connection, so it can't be done
from this repo alone. Steps 1-8 below are that setup checklist.

## Required PHP extensions

Nixpacks builds the PHP environment from the `ext-*` platform requirements
declared in `composer.json` (`ext-gd`, `ext-intl`, `ext-pdo_mysql`,
`ext-zip`). These are not optional: Filament needs `intl`, spreadsheet
exports (openspout) need `zip`, image handling needs `gd`, and the MySQL
connection (including the Pre-Deploy `migrate`) needs `pdo_mysql`. If you add
a dependency that needs another extension, declare it in `composer.json` and
run `composer update --lock` so Nixpacks installs it on the next build.

## 1. Create the project and connect the repo

In Railway: **New Project → Deploy from GitHub repo** → select this
repository. Railway auto-detects it as a PHP/Laravel app.

## 2. Add a MySQL database

**New → Database → Add MySQL** on the project canvas. Railway provisions it
and exposes connection details as service variables (referenced below as
`${{MySQL.MYSQL_URL}}` etc. — the exact variable names are visible on the
MySQL service's **Variables** tab once it's created).

## 3. Add a Redis instance

**New → Database → Add Redis** on the project canvas, the same way as the
MySQL step above. Railway provisions it and exposes its connection details
as service variables — referenced below as `${{Redis.REDIS_URL}}` (confirm
the exact variable name on the Redis service's **Variables** tab once it's
created, same caveat as `MYSQL_URL` above). This is the cache/session/queue
backend for production (see the env var table in step 5).

## 4. Add a persistent Volume for uploaded files

Product images, seller documents, and spec-sheet PDFs are written to
`public/storage` (Laravel's `public` disk — see `CLAUDE.md`; deliberately
`public/storage` directly, not the usual `storage/app/public` +
`storage:link` symlink — see the troubleshooting section at the bottom of
this file for why). Railway's container filesystem is wiped on every
deploy, so without a Volume every upload would be lost on the next deploy.

On the app service (not the MySQL service): **⌘K / right-click → Add
Plugin → Volume**, then set its **mount path** to:

```
/app/public/storage
```

## 5. Set the app service's environment variables

On the app service's **Variables** tab:

| Variable | Value | Why |
|---|---|---|
| `APP_KEY` | generate locally with `php artisan key:generate --show`, paste the output | Laravel refuses to boot without one |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Required** — this project has 3 known, accepted `laravel/framework` advisories that are only exploitable in debug mode (see `CLAUDE.md` "Known issues") |
| `APP_URL` | your Railway-assigned or custom domain, e.g. `https://your-app.up.railway.app` | used to build absolute URLs (RFQ/seller emails, sitemaps, etc.) |
| `APP_TIMEZONE` | `Asia/Kolkata` | this is an India-based business; pricing/GST features assume IST |
| `DB_CONNECTION` | `mysql` | |
| `DB_URL` | `${{MySQL.MYSQL_URL}}` (reference Railway's MySQL service variable — exact name per step 2) | |
| `SESSION_DRIVER` | `redis` | rides on the `redis` cache store (Laravel's `SessionManager` resolves sessions through `cache()->store('redis')`) — same durability across redeploys as the old `database` driver, without hitting MySQL on every request |
| `CACHE_STORE` | `redis` | offloads cache reads/writes from MySQL |
| `QUEUE_CONNECTION` | `redis` | this app queues its outbound emails (seller activation/approval/rejection, product-listing notifications, RFQ notifications — see `CLAUDE.md`), so a real queue backend is required; needs the queue-worker service in step 7 actually running to process jobs |
| `REDIS_URL` | `${{Redis.REDIS_URL}}` (reference Railway's Redis service variable — exact name per step 3) | Laravel's `ConfigurationUrlParser` derives host/port/password from this URL and those values take precedence over any discrete `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD` vars, so `REDIS_URL` alone is sufficient — no need to set the discrete vars in production |
| `REDIS_CLIENT` | `predis` | this app uses the pure-PHP `predis/predis` client (no native `ext-redis` compile needed) |
| `LOG_CHANNEL` | `stderr` | so logs show up in Railway's log viewer |
| `LOG_STDERR_FORMATTER` | `\Monolog\Formatter\JsonFormatter` | structured logs |
| `FILESYSTEM_DISK` | `local` | unchanged from local dev — the Volume from step 4 makes this durable |
| mail vars (`MAIL_MAILER`, `MAIL_HOST`, etc.) | your real SMTP provider's credentials | `.env.example` has the full list; in local dev these are usually unset (mail defaults to writing to the log) — production needs real ones or queued emails silently never send |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | your production reCAPTCHA keys | only if the RFQ form's recaptcha check is enabled — see `.env.example` |

Do not copy values from your local `.env` — generate fresh credentials for
production, particularly `APP_KEY` and any mail/recaptcha secrets.

Use Railway's **Shared Variables** (project/environment level, not just this
one service) for anything the queue-worker service in step 7 also needs —
`APP_KEY`, `DB_URL`, `REDIS_URL`, `REDIS_CLIENT`, mail vars, `APP_ENV`,
`APP_TIMEZONE`, `LOG_CHANNEL` — so rotating a credential updates both
services at once instead of drifting.

## 6. Set the Pre-Deploy Command

On the app service's **Settings → Deploy** tab, set **Pre-Deploy Command**
to:

```
chmod +x ./railway/init-app.sh && sh ./railway/init-app.sh
```

This runs `railway/init-app.sh` (checked into this repo) after each build,
before traffic is routed to the new instance: applies any new migrations
(non-destructively — `migrate --force`, never `migrate:fresh`) and rebuilds
Laravel's config/route/view/event caches. It deliberately does **not** run
`storage:link` — see the troubleshooting section below for why that doesn't
work here.

## 7. Add a queue-worker service

Queued emails (step 5) need something running `php artisan queue:work` to
actually process them. Railway doesn't run a multi-process `Procfile`
without a Dockerfile/custom buildpack (out of scope for this app), so this
is a **second Railway service** from the same repo:

- **New → GitHub Repo** → the same repository, same branch, as a second
  service in this project (not a new project).
- **Settings → Deploy → Custom Start Command**:
  ```
  php artisan queue:work --tries=3 --backoff=10 --max-time=3600
  ```
  `--max-time=3600` recycles the worker process hourly so it doesn't hold
  stale code or accumulate memory indefinitely between deploys — Railway's
  restart policy brings it straight back up.
- **Settings → Deploy → Restart Policy**: always/on-failure, so a crash
  (OOM, a transient DB blip) self-heals without manual intervention.
- This service has **no public domain and doesn't listen on a port** — it
  isn't an HTTP service. Don't generate a domain for it, and disable/skip
  any port-based health check Railway tries to apply.
- **Environment variables**: the same set as the app service (see the
  Shared Variables note at the end of step 5) — `APP_KEY`, `DB_URL`,
  `REDIS_URL`, `REDIS_CLIENT`, mail vars, `APP_ENV`, `APP_TIMEZONE`,
  `LOG_CHANNEL`, etc.
- **Pre-Deploy Command**: the same as the app service —
  ```
  chmod +x ./railway/init-app.sh && sh ./railway/init-app.sh
  ```
  Running `migrate --force` here too is cheap and idempotent, and it
  guarantees the worker never starts against an unmigrated schema
  regardless of which of the two services finishes deploying first.

Why a second service rather than a second process in the existing one:
Railway (without Docker) runs one start command per service, so a
background worker that needs to run continuously alongside the web
process has to be its own service.

## 8. Deploy

Push to `master` (or trigger a manual deploy from the Railway dashboard).
Watch the deploy logs for the Pre-Deploy Command output to confirm
migrations applied cleanly, on **both** the app service and the
queue-worker service.

## Operating the queue

- `php artisan queue:failed` — list jobs (e.g. a queued email) that
  exhausted their retries. Run it via `railway run php artisan queue:failed`
  from your machine, or the service's Railway-provided shell.
- `php artisan queue:retry {id}` / `php artisan queue:retry all` — replay a
  failed job (or all of them) once the underlying issue is fixed.
- Redeploying the queue-worker service naturally restarts `queue:work` (a
  Railway redeploy is a new container), so there's no manual restart step
  after shipping a change to a Mailable or other queued job — a gotcha
  worth remembering if you're used to `queue:listen`-style tooling that
  auto-reloads on file change (this app doesn't use that in production).

## Ongoing: applying new migrations safely

Every subsequent deploy re-runs `railway/init-app.sh` automatically via the
Pre-Deploy Command, so new migrations merged to `master` apply themselves —
you don't need to SSH in or run anything by hand. **Never** manually run
`php artisan migrate:fresh` against this Railway database once it holds
real data (see `CLAUDE.md`'s `migrate` vs `migrate:fresh` warning) — it
would wipe every product image, seller account, and quote request.

## Troubleshooting: issues hit during initial setup

Every one of these showed up, in this order, getting this app's first
deployment live. Listed in case the same setup is repeated (a second
environment, a new project) or any of them regress.

**"Branch 'main' not found in `{org}/inventory`"** (banner on the project
canvas) — the Railway service was tracking `main`, but this repo's default
branch is `master`. Fix: service → **Settings → Source → Branch** → set to
`master`.

**Deploy log: `error creating app: directory .../snapshot-target-unpack/master
does not exist`** — happened immediately after switching the tracked branch,
on multiple different Railway builder machines (ruling out a one-off
builder glitch). The service's GitHub source binding was stale from before
the branch change. Fix: **Settings → Source** → **Disconnect** the repo,
then reconnect it fresh (repo + `master` branch + blank root directory).

**"GitHub Repo not found"** (red banner under Source Repo in service
settings) — Railway's GitHub App had lost/never had access to the repo for
that specific service. Fix: on GitHub, **Settings → Applications → Railway
→ Configure**, confirm the repo is in the App's access list; then
disconnect/reconnect the source on the affected Railway service.

**`SQLSTATE[HY000] [2002] Connection refused` connecting to `mysql`** on
every boot — an env var was set as `MYSQL_URL=${{MySQL.MYSQL_URL}}`, but
Laravel's `config/database.php` reads the connection string from **`DB_URL`**,
not `MYSQL_URL` — that variable name is never read by the framework, so the
`mysql` connection silently fell back to its hardcoded default
(`127.0.0.1:3306`), which nothing listens on in the container. Fix: name the
variable `DB_URL` (see the table in step 5 above), not `MYSQL_URL` or
anything else.

**Site loads but `/` (and everything else) 404s** — migrations had applied
(the Pre-Deploy Command handles that), but the database was otherwise empty,
so `PageController` found no published `Page` with slug `home` and
correctly returned 404. This is expected on a brand new database — the
Pre-Deploy Command deliberately never seeds (seeding isn't idempotent/safe
to run blindly on every deploy). Fix: run once via the service's **Console**
tab:
```
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=StaffSeeder
php artisan db:seed --class=CatalogSeeder   # optional — sample/demo catalog data, skip for a real launch
php artisan db:seed --class=PageSeeder
php artisan db:seed --class=NavItemSeeder
```
`RoleSeeder`/`StaffSeeder`/`PageSeeder`/`NavItemSeeder` use `firstOrCreate`
and are safe to re-run. `CatalogSeeder` is **not** — it calls
`Product::factory()->create()` / `Category::factory()->create()` directly,
so running it a second time throws a unique-constraint error on the
`(category_id, slug)` / `(parent_id, slug)` indexes. Only run it once, or
delete its rows first (`Demo Supplier Co.` seller, its categories/products).

**Hyperlinks render Bootstrap's default blue instead of the brand orange**
— `public/css/site.css` set `--bs-link-color`, but Bootstrap 5.3's actual
CSS rule for `<a>` reads `--bs-link-color-rgb` (an RGB triplet, not a hex
custom property):
`a{color:rgba(var(--bs-link-color-rgb),var(--bs-link-opacity,1))}`. The hex
variable was simply never read. Fixed in `public/css/site.css` by also
setting `--bs-link-color-rgb` / `--bs-link-hover-color-rgb`.

**`/admin` and `/seller/login` render with zero CSS** (raw unstyled HTML) —
Filament's asset `<link>` tags were being generated as `http://...` on a
page served over `https://`, which browsers block outright as mixed content
(CSS is treated as blockable, not just upgradable). Root cause:
`bootstrap/app.php` never called `trustProxies()`. Railway (like every PaaS)
terminates TLS at its edge and forwards plain HTTP internally, setting
`X-Forwarded-Proto: https` — without trusting that header, Laravel's
`asset()`/`url()` helpers fall back to the untrusted request's scheme
(`http`), regardless of what `APP_URL` is set to. Fixing `APP_URL` alone
does **not** fix this — `asset()` prefers the live request's detected
scheme over `APP_URL` whenever a request is active. Fixed by adding
`$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.

**Uploaded product images/spec-sheet PDFs 404 even though the upload
"succeeds"** — the real bug, and the reason step 4's mount path and
`config/filesystems.php` no longer match older versions of this doc.
`Storage::disk('public')->exists(...)` confirmed the uploaded file was
genuinely on disk under `storage/app/public/...`, but `public/storage` (the
symlink `storage:link` is supposed to create) didn't exist at all in the
container serving requests — `ls public/storage` → "No such file or
directory". Cause: Railway's Pre-Deploy Command runs in a **separate,
throwaway container** that's discarded before the container that actually
serves traffic boots (visible in the deploy log as two distinct `Starting
Container` events around the Pre-Deploy step). `storage:link`'s symlink was
created in the throwaway one and never existed in the real one — this had
been silently broken since the very first deploy; it just never surfaced
until a real file (the seeded demo products have no images) was requested
through it.

Fixed by removing the symlink from the equation entirely rather than
chasing where to run `storage:link` instead: `config/filesystems.php`'s
`public` disk now points straight at `public_path('storage')` instead of
`storage_path('app/public')` + a symlink, `storage:link` was dropped from
`railway/init-app.sh`, and the Volume's mount path changed from
`/app/storage/app/public` to `/app/public/storage` (same persistent volume,
just remounted — no data migration needed). If you set this up before this
fix landed, update the Volume's mount path in Railway's dashboard to match
and redeploy.
