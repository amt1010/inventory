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
