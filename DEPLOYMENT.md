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
`storage/app/public` (Laravel's `public` disk — see `CLAUDE.md`). Railway's
container filesystem is wiped on every deploy, so without a Volume every
upload would be lost on the next deploy.

On the app service (not the MySQL service): **⌘K / right-click → Add
Plugin → Volume**, then set its **mount path** to:

```
/app/storage/app/public
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
(non-destructively — `migrate --force`, never `migrate:fresh`), recreates
the `public/storage` symlink, and rebuilds Laravel's config/route/view/event
caches.

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
