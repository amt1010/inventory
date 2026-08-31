# Email Templates & Password Reset — Review Findings and Rulings

Date: 2026-08-31
Branch: `email-templates-and-password-reset`
Plans: `docs/superpowers/plans/2026-08-31-email-template-admin-editing.md` (15 tasks),
`docs/superpowers/plans/2026-08-31-self-service-password-reset.md` (5 tasks)
Specs: `docs/superpowers/specs/2026-08-31-email-template-admin-editing-design.md`,
`docs/superpowers/specs/2026-08-31-self-service-password-reset-design.md`

Both plans were executed via subagent-driven development: a fresh implementer
subagent per task, a spec-compliance + code-quality review after each, and a
final whole-branch review per plan. This document is the durable record of
what those reviews found and how each finding was resolved — the per-task
ledgers themselves were scratch state (git-ignored, deleted once each plan
finished cleanly) and are not otherwise preserved anywhere.

## Plan A — Admin-Editable Email Templates

### Per-task findings

- **Task 3 (seed the 8 system templates).** Two seeded template bodies
  (`product_listing_live`, `seller_rejected`) were missing blank lines
  between block elements, compared to the original hardcoded Blade views.
  Traced to the plan's own authored seeder code (a compact-heredoc style),
  not an implementer deviation.
  **Ruling:** accepted, no fix. HTML collapses inter-tag whitespace
  identically in every rendering client regardless of source blank lines,
  and the pre-existing regression tests assert via `assertSeeInHtml()`
  substring checks, never exact-body equality — no test, now or later,
  distinguishes the two forms. The "byte-identical rendering" requirement is
  about meaningful rendered content, not literal source whitespace.

- **Task 4 (port `ProductListingLive`).** The implementer's first attempt
  added a global `setUp()` hook to `tests/TestCase.php`, auto-seeding
  `EmailTemplateSeeder` for every `RefreshDatabase` test in the ~500-test
  suite, to keep a pre-existing test (`ProductListingLiveMailTest`) from
  throwing `ModelNotFoundException` now that the ported mailable depends on
  a seeded row.
  **Ruling:** reverted the global hook entirely — disproportionate blast
  radius, and inconsistent with the per-test-class seeding pattern every
  other task in this plan uses. Fixed by adding a scoped `setUp()` seed call
  to the one pre-existing test file that actually needed it instead.

- **Task 11 (full-suite regression check).** A full, unfiltered
  `php artisan test` run — not just the per-task filtered runs — caught a
  real regression the per-task reviews couldn't see:
  `tests/Feature/QuoteRequestSubmissionTest.php` (pre-existing, untouched by
  Tasks 1–10) had 2 failures. Root cause: `Mail::assertQueued($class, fn
  ($mail) => $mail->hasTo(...))` calls `hasTo()`, which builds the
  mailable's envelope internally — triggering `EmailTemplate::forKey()` even
  under `Mail::fake()`, same as direct rendering does. Grepped the whole
  `tests/` tree for the same pattern (`->hasTo(`, `->hasCc(`, `->hasBcc(`,
  `->hasSubject(`, `->hasFrom(`) to confirm no other hidden instances.
  Fixed with the same scoped `setUp()` seed pattern; full suite green
  afterward.

- **Task 13 (`EmailTemplateResource` UI).** The implementer touched an
  unauthorized 5th file, `tests/Feature/AdminNavigationOrderTest.php` (one
  assertion line), because the new resource's `navigationSort = 9` ties with
  `StaffResource`'s existing value, changing alphabetical sidebar order.
  **Ruling:** accepted as a necessary, minimal, well-disclosed exception —
  verified independently (direct read of `StaffResource.php`) that the
  collision is real and the fix is exactly one line.

### Final whole-branch review (opus)

**Critical (fixed):** nothing seeds `email_templates` or the new RBAC
permission area on deploy — reproduces a documented production-incident
pattern already in `DEPLOYMENT.md` (the `audit_logs.full` incident). Fixed
by adding `EmailTemplateSeeder` to the first-deploy checklist and a new
troubleshooting entry.

**Important (all fixed):**
1. Subject lines were HTML-escaped via the same `e()` call used for HTML
   bodies, corrupting apostrophes/ampersands in every ported mailable's
   subject (e.g. `O'Brien & Sons` → `O&#039;Brien &amp; Sons`). Fixed by
   adding an `escapeHtml` parameter to `EmailTemplateRenderer::render()`,
   defaulting to escaped (bodies) with all 7 subject call sites passing
   `escapeHtml: false`.
2. Token help text and preview sample data were two independently
   maintained arrays in `EmailTemplateResource`, already out of sync
   (`tokenHelpFor('quote_request_received')` was missing 4 tokens the
   seeded body actually uses). Fixed by consolidating into one `TOKEN_MAP`
   constant plus one `tokenSampleValues()` method.
3. CC/BCC form fields accepted malformed input silently (contrary to the
   spec). Fixed with a validation rule matching
   `EmailTemplate::parseAddressList()`'s own logic.

**Deliberately deferred (not fixed in this pass):** a suggested trait-based
refactor to memoize the double `EmailTemplate::forKey()` lookup per mailable
send and eliminate ~85 lines of near-identical `envelope()`/`content()`
boilerplate across 7 files — real, but judged too risky to attempt in a
one-shot final-review fix wave with no second review pass available. Also
deferred: a lossy RichEditor round-trip for one seeded template's inline
`style` attribute (no functional break); a custom template could
theoretically squat a future system key (low-probability edge case); Publish
acts on the last-saved draft rather than unsaved form state (UX nuance, not
a defect); several cosmetic import/style inconsistencies; an explicitly
non-regression XSS note (consistent with this app's existing pattern of
rendering RichEditor content unescaped on public pages, not something this
branch introduced or widened).

## Plan B — Self-Service Password Reset

### Per-task findings

- **Task 1 (3 reset-email templates).** The task's own brief was already
  stale by the time it executed — Plan A's final-review fix wave (which
  happened after Plan B was written but before Plan B ran) had changed
  `EmailTemplateResource`'s token-help/sample-data methods from two inline
  arrays to a `TOKEN_MAP` constant + `tokenSampleValues()` method.
  **Ruling:** corrected the brief's instructions at dispatch time, before
  the implementer could get stuck on code that no longer existed in that
  shape.

- **Task 3 (seller Clerk-only guard).** Discovered mid-task that
  `tests/Feature/SellerPasswordResetTest.php` was not a new file — it
  already existed from an unrelated session back in July (commit `c4eaa24`,
  which built the baseline seller-reset feature this task extends). The
  task's brief incorrectly said "create" this file; it should have said
  "modify." Following the brief literally, the implementer replaced the
  whole file with only the 3 new tests, silently dropping all 4 pre-existing
  ones. 2 of the 4 were legitimate replacements (they tested the
  default-notification behavior this task deliberately replaces with a
  custom Mailable) — but the other 2 (a broker-config assertion, an HTTP
  route reachability check) were unrelated to this task's actual change and
  represented a real coverage loss. Caught via the full-suite test count
  unexpectedly *dropping* (622 → 621) despite 3 new tests being added.
  **Fixed** through the normal task-review fix loop (not a final-review-style
  one-shot ruling, since this was mid-task): both lost tests restored
  verbatim; re-review confirmed clean.

### Final whole-branch review (opus)

**Important (all fixed):**
1. The buyer's "we've sent a reset link" confirmation message was flashed to
   the session but never rendered — `login.blade.php` had no
   `session('status')` consumer. A buyer submitting the forgot-password form
   saw a silent redirect with zero feedback. Fixed by adding the same
   `@if (session('status'))` block `forgot-password.blade.php` already had.
2. Both new Filament auth-page overrides (staff and seller) silently dropped
   Filament's own `canAccessPanel()` guard from the mail-send callback — a
   real regression against the *pre-existing* (July) seller flow: a
   pending/rejected seller requesting a reset used to get the generic "sent"
   response with no actual email; after this branch's first pass, they'd get
   a real email with a working-looking but ultimately dead link (the token
   still gets consumed; completing the reset later fails). Fixed by
   restoring the guard in both files — mail-sending only, the user-visible
   response stays identical for every account state either way.
3. The two new buyer password-reset forms had no reCAPTCHA, unlike every
   other public buyer-facing form in this app — an unauthenticated endpoint
   that triggers outbound mail on demand is exactly what reCAPTCHA exists to
   gate here. Fixed by adding the same rule/widget pattern used elsewhere.
4. `DEPLOYMENT.md`'s email-template troubleshooting entry (added by Plan A's
   own fix wave) only covered "fresh migration, never seeded" — not "new
   keys added to an already-seeded table," which is exactly what this plan's
   Task 1 did. Generalized the entry to cover both cases and name the 3 new
   password-reset mailables.

**Deliberately deferred (not fixed in this pass):** an account-enumeration
oracle at the reset-*completion* step (distinct error messages for "unknown
email" vs. "invalid token") — explicitly matches stock Laravel/Filament
behavior and the spec scopes anti-enumeration to the request step only, not
completion; a timing side-channel on the request step (bcrypt cost
difference between eligible/ineligible accounts) — standard Laravel
behavior, largely masked by queued sends in production, not asked to be
fixed; a couple of test-coverage gaps (no throttle test on the `POST
/reset-password` route, no HTTP-reachability test for `/admin/password-reset/request`
mirroring the one restored for sellers); the staff `must_change_password`
listener is a closure rather than a named listener class (not
`event:cache`-compatible, but this app doesn't use `event:cache` today).

**Process note:** the fix-wave implementer subagent hit a monthly
spend-limit/rate error partway through this final round (after 4 of 6 fixes
were applied, before running tests or committing). The controlling session
inspected the interrupted agent's uncommitted working-tree changes directly
— reading every diff before trusting it — confirmed the first 4 fixes were
correctly applied, then completed the 2 remaining trivial fixes (a
`DEPLOYMENT.md` wording generalization, one stale test-method rename)
directly rather than risk a second failed dispatch on the same limit. Both
were text-only, low-risk edits; the completed fix wave was still sent
through a normal scoped re-review afterward, which came back clean.

## Outcome

Both final whole-branch reviews came back clean after their respective fix
waves and scoped re-reviews. Full test suite: **629 passed, 1766 assertions,
0 failures.**
