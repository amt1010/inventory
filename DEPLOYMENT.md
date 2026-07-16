# Deploying to Railway

This app is deployed to [Railway](https://railway.com), which runs Laravel
natively via Nixpacks (auto-detected PHP-FPM + Caddy — no Dockerfile,
`Procfile`, or `nixpacks.toml` needed in this repo).

Everything in this file is a one-time setup you do in Railway's dashboard —
it needs your Railway account and GitHub connection, so it can't be done
from this repo alone. Steps 1-6 below are that setup checklist.

## 1. Create the project and connect the repo

In Railway: **New Project → Deploy from GitHub repo** → select this
repository. Railway auto-detects it as a PHP/Laravel app.

## 2. Add a MySQL database

**New → Database → Add MySQL** on the project canvas. Railway provisions it
and exposes connection details as service variables (referenced below as
`${{MySQL.MYSQL_URL}}` etc. — the exact variable names are visible on the
MySQL service's **Variables** tab once it's created).

## 3. Add a persistent Volume for uploaded files

Product images, seller documents, and spec-sheet PDFs are written to
`storage/app/public` (Laravel's `public` disk — see `CLAUDE.md`). Railway's
container filesystem is wiped on every deploy, so without a Volume every
upload would be lost on the next deploy.

On the app service (not the MySQL service): **⌘K / right-click → Add
Plugin → Volume**, then set its **mount path** to:

```
/app/storage/app/public
```

## 4. Set the app service's environment variables

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
| `SESSION_DRIVER` | `database` | file-based sessions wouldn't survive a redeploy; the `sessions` table already exists via the default migration, no extra migration needed |
| `CACHE_STORE` | `database` | same reasoning; `cache`/`cache_locks` tables already exist via the default migration |
| `QUEUE_CONNECTION` | `database` | this app doesn't currently dispatch queued jobs (all email sends are synchronous, best-effort try/catch — see `CLAUDE.md`), but `database` is a safe default if that changes; the `jobs`/`failed_jobs` tables already exist |
| `LOG_CHANNEL` | `stderr` | so logs show up in Railway's log viewer |
| `LOG_STDERR_FORMATTER` | `\Monolog\Formatter\JsonFormatter` | structured logs |
| `FILESYSTEM_DISK` | `local` | unchanged from local dev — the Volume from step 3 makes this durable |
| mail vars (`MAIL_MAILER`, `MAIL_HOST`, etc.) | your real SMTP provider's credentials | `.env.example` has the full list; in local dev these are usually unset (mail defaults to writing to the log) — production needs real ones or RFQ/seller notification emails silently never send |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | your production reCAPTCHA keys | only if the RFQ form's recaptcha check is enabled — see `.env.example` |

Do not copy values from your local `.env` — generate fresh credentials for
production, particularly `APP_KEY` and any mail/recaptcha secrets.

## 5. Set the Pre-Deploy Command

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

## 6. Deploy

Push to `master` (or trigger a manual deploy from the Railway dashboard).
Watch the deploy logs for the Pre-Deploy Command output to confirm
migrations applied cleanly.

## Ongoing: applying new migrations safely

Every subsequent deploy re-runs `railway/init-app.sh` automatically via the
Pre-Deploy Command, so new migrations merged to `master` apply themselves —
you don't need to SSH in or run anything by hand. **Never** manually run
`php artisan migrate:fresh` against this Railway database once it holds
real data (see `CLAUDE.md`'s `migrate` vs `migrate:fresh` warning) — it
would wipe every product image, seller account, and quote request.
