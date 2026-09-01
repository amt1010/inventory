# Session History

Append-only log of working sessions in this repo. Newest entries at the bottom.
Generated/maintained via `scripts/log-session.sh` — see SESSION-LOGGING.md.

---

## 2026-08-30 15:39 UTC — Clerk Google sign-in: implementation complete, review findings fixed, branch merged to master

- Branch: `master`
- Commit: `466df20`

## Goal
Implement "Sign in with Google" via Clerk for buyer register/login and seller
register/panel-login, per docs/superpowers/plans/2026-08-30-clerk-google-auth.md,
using subagent-driven-development.

## Changes
- Clerk dependency/config scaffolding, clerk_user_id + nullable password on
  users/sellers, ClerkAuthenticator (JWT verification against Clerk JWKS +
  Backend API identity fetch), buyer and seller Clerk auth endpoints, seller
  registration Clerk-prefilled path, shared frontend plumbing (layout script
  tag, button partial, completion page), buttons wired into buyer/seller
  entry-point pages and the Filament seller panel login page.
- Fix wave from code review: CSP allowance for Clerk's frontend API host
  (script-src/connect-src/frame-src), require verification.status === 'verified'
  on the primary email (not just non-blank), validate the `iss` claim on the
  session token, require an oauth_google external account server-side (not
  just client-side), bust+retry the JWKS cache once on decode failure (key
  rotation self-heals), and replaced ClerkAuthenticatorTest's runtime
  openssl_pkey_new()/export() key generation with static fixture RSA keypairs
  (was failing locally without OPENSSL_CONF pointed at XAMPP's cnf).

## Decisions
- Work happened in a worktree (worktree-feature+clerk-google-signin) off
  master rather than the plan's suggested feature/clerk-google-signin branch
  name; merged back to master via fast-forward, worktree and branch cleaned
  up afterward.

## Open items / next steps
- Local commits are not yet pushed to origin/master (17 commits ahead) —
  push needs explicit go-ahead.
- Per the plan's Task 12 Step 4: no amount of the mocked/faked unit and
  feature test coverage here substitutes for a real end-to-end check —
  buyer register, buyer login, seller register, and seller panel login
  still need manual verification against a real Clerk application with an
  actual Google account before this ships.

---

## 2026-08-31 17:14 UTC — Add admin-editable email templates and self-service password reset

- Branch: `email-templates-and-password-reset`
- Commit: `b84501f`
- Working tree at log time:
```
 M public/css/filament/forms/forms.css
 M public/js/filament/filament/app.js
 M public/js/filament/filament/echo.js
 M public/js/filament/forms/components/color-picker.js
 M public/js/filament/forms/components/date-time-picker.js
 M public/js/filament/forms/components/file-upload.js
 M public/js/filament/forms/components/key-value.js
 M public/js/filament/forms/components/markdown-editor.js
 M public/js/filament/forms/components/rich-editor.js
 M public/js/filament/forms/components/select.js
 M public/js/filament/forms/components/tags-input.js
 M public/js/filament/forms/components/textarea.js
 M public/js/filament/notifications/notifications.js
 M public/js/filament/support/support.js
 M public/js/filament/tables/components/table.js
 M public/js/filament/widgets/components/chart.js
 M public/js/filament/widgets/components/stats-overview/stat/chart.js
?? docs/superpowers/reviews/
```

## Goal
Let Admin/Content Editor staff edit and publish the content of the app's
transactional emails from a new admin screen, and close the self-service
password-reset gap across all three auth guards (staff had none, sellers had
no guard against Clerk-only accounts, buyers had none at all).

## Changes
- New `email_templates` table + `EmailTemplate` model (draft/live column
  pairs, `publish()`/`resetDraft()`/`isModified()`), a small
  `EmailTemplateRenderer` token-substitution service (no Blade compilation —
  admin-authored content never executes as code), and a Filament
  `EmailTemplateResource` at `/admin/email-templates` (draft editing,
  Publish/Reset Draft, custom template create/delete, a Preview action).
- 8 of the 9 existing transactional Mailables ported to render from the DB
  instead of hardcoded Blade views (`product-edit-ready-for-acceptance` and
  `seller-import-stuck` stay hardcoded — no real "copy" to edit in either).
- Self-service password reset for staff (new, with a `must_change_password`
  interaction against the existing admin-triggered reset), sellers (a
  Clerk-only guard added to the pre-existing flow, plus swapped Filament's
  default notification for a template-driven one), and buyers (entirely
  new — controller, routes, views, reusing a previously-unused `users`
  broker).
- `docs/superpowers/reviews/2026-08-31-email-templates-and-password-reset-review.md`
  records every review finding and ruling from both plans' execution.

## Decisions
- Two plans, executed in dependency order (email templates first) via
  subagent-driven development in an isolated worktree/branch
  (`email-templates-and-password-reset`, off `master`).
- A full, unfiltered `php artisan test` run after all Mailable ports (not
  just per-task filtered runs) caught a real regression per-task review
  couldn't see: `Mail::assertQueued(..., fn ($m) => $m->hasTo(...))`
  triggers envelope-building even under `Mail::fake()`.
- Discovered mid-execution that a "new" test file for the seller
  password-reset task already existed from an unrelated July session —
  corrected the plan's stale assumption and restored 2 tests that had been
  silently dropped as a result.
- Two final whole-branch reviews (one per plan) each found real issues
  (subject-line HTML-escaping, a missing production seed step, a dropped
  Filament `canAccessPanel()` guard, missing reCAPTCHA, an unrendered
  confirmation message) — all fixed and re-reviewed clean. Full details in
  the review-findings doc above rather than duplicated here.

## Open items / next steps
- PR open at https://github.com/amt1010/inventory/pull/53, not yet merged.
- Manual click-through in a real environment (admin template editing,
  publish flow, all three password-reset flows end to end) still worth
  doing before this ships to production, same as any Filament-heavy change.
- The final review's deferred trait-refactor suggestion (memoize the
  double `EmailTemplate::forKey()` lookup per mailable, ~85 lines of
  boilerplate across 7 files) is a legitimate follow-up, not done here.

---

## 2026-08-31 17:57 UTC — Fix issues #54/#55: automate RoleSeeder+EmailTemplateSeeder in Pre-Deploy Command

- Branch: `master`
- Commit: `b43b81a`
- Working tree at log time:
```
 M DEPLOYMENT.md
 M railway/init-app.sh
```

## Goal
Fix two issues logged at https://github.com/amt1010/inventory/issues:
- #54: emails not sent (staff invitation, seller activation)
- #55: Email Template section not visible in Admin Dashboard

## Changes
- railway/init-app.sh: run `db:seed --class=RoleSeeder --force` and
  `db:seed --class=EmailTemplateSeeder --force` after `migrate --force`,
  on every Pre-Deploy run (both seeders are idempotent).
- DEPLOYMENT.md: updated step 6 to describe the new automatic seeding,
  and updated both related troubleshooting entries (audit_logs.full /
  RBAC nav-hiding, and the email-template ModelNotFoundException entry)
  to note the fix and keep them as historical diagnosis reference.

## Decisions
- Root cause for both issues was the same gap: RoleSeeder and
  EmailTemplateSeeder are documented as safe/idempotent to re-run, but
  the Pre-Deploy Command never called them, so a new permission area
  (email_templates.*) or new template key added in a later deploy never
  reached production until someone remembered to run it manually via the
  Railway Console. A prior review (docs/superpowers/reviews/2026-08-31-
  email-templates-and-password-reset-review.md) had already hit this
  exact gap once and fixed it with documentation only, which is why it
  recurred as these two new issues. Automating both seeders in the
  Pre-Deploy script closes the whole class of bug instead of relying on
  someone remembering a manual step again.
- Did not add StaffSeeder/PageSeeder/NavItemSeeder to the Pre-Deploy
  script (unrelated to these issues, out of scope) and explicitly did
  not add CatalogSeeder (documented as non-idempotent, would duplicate
  demo data on every deploy).

## Open items / next steps
- The existing production database still needs a one-time catch-up run
  of both seeders (this fix only prevents recurrence going forward) —
  requires Railway Console access, which this session doesn't have:
  php artisan db:seed --class=RoleSeeder --force
  php artisan db:seed --class=EmailTemplateSeeder --force
- Full test suite: 629 passed, 1766 assertions, 0 failures (unchanged —
  this fix only touches deploy script + docs, no app code).

---

## 2026-09-01 03:42 UTC — Pull latest from origin/master, apply new migrations, verify full test suite

- Branch: `master`
- Commit: `05d2059`

## Goal
Sync local master with origin/master and confirm the pulled changes are safe.

## Changes
- Fast-forwarded local master from 6fc3034 to 05d2059 (clean merge, no conflicts)
- Pulled in: routes/api.php (new), RoleSeeder.php updates, bootstrap/app.php, railway/init-app.sh
- Ran `php artisan migrate` — applied 4 new migrations (Clerk fields on users/sellers, email_templates table, staff_password_reset_tokens table)

## Decisions
- Used `php artisan migrate` (not migrate:fresh) per project convention — dev DB holds real data

## Open items / next steps
- None; full test suite green (641 passed, 1808 assertions)
