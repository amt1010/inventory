# Clerk Google Sign-In for Buyers and Sellers — Design Spec

Date: 2026-08-30
Status: Approved

## Purpose

Buyers register at `/register` and sellers register at `/seller/register`
(linked from the `/sellers` landing page) with a plain email + password
form. This spec adds "Sign in with Google" as an alternative on both sides,
via Clerk, without touching the existing password path.

The two sides behave differently on purpose:

- **Buyer** accounts only need a name and email. Google auth is enough by
  itself to finish registration and log the buyer in — no extra screen.
- **Seller** accounts need company name, GST number, business address, and
  supporting documents before the account means anything. Google auth
  establishes identity; the seller still has to fill out the registration
  form to complete registration.

Apple Sign-In is out of scope. Staff (`/admin`) is untouched.

## Why Clerk-as-identity-broker, not Clerk-as-session-manager

This app runs three separate Laravel session guards (`web`, `seller`,
`staff`), each with its own Eloquent provider, and every authorization
boundary — Policies, Filament panel access, `auth('web')->id()` ownership
scoping — is built directly on top of those guards (see CLAUDE.md). Handing
session management to Clerk would mean reworking all of that to recognize
Clerk sessions instead of, or alongside, Laravel's own.

Instead, Clerk's job stops at proving "this browser controls this Google
account, with this verified email." Once we have that, the rest of the app
proceeds exactly as it does today: create or find a local `User`/`Seller`
row and log into the existing guard. Filament, Policies, and every ownership
check need zero changes.

There is no official Clerk Laravel SDK, so the mechanism is:

1. Clerk's vanilla JS SDK (`@clerk/clerk-js`, loaded from Clerk's CDN — no
   build step) runs on the register/login pages, restricted to the
   `oauth_google` strategy only.
2. On success, JS calls `window.Clerk.session.getToken()` and POSTs it to a
   new Laravel endpoint.
3. The backend verifies the token's signature against Clerk's JWKS using
   `firebase/php-jwt` (standard, actively maintained, no unofficial Clerk
   PHP package involved), checking `iss` and `exp`.
4. The backend then calls Clerk's Backend API, `GET /v1/users/{id}`, with
   the secret key to fetch the verified email and name for that `sub`.
5. From here it's plain Laravel: find-or-create the local row, log into the
   guard, redirect.

## Schema changes

Both `users` and `sellers` get:

- `clerk_user_id` — nullable, unique string. Null for anyone who registered
  with email/password and never linked Google.
- `password` — made nullable. A Clerk-only account has no local password.

Two migrations: `add_clerk_user_id_to_users_table`,
`add_clerk_user_id_and_nullable_password_to_sellers_table`.

## Buyer flow (`/register`, `/login`)

**Register via Google:**

1. Clerk verifies the Google identity.
2. Backend looks up `User` by `clerk_user_id`.
3. Not found → look up by `email`. Found → attach `clerk_user_id` to that
   existing (password) account instead of erroring or duplicating.
4. Still not found → create a new `User` with `clerk_user_id`, `name`,
   `email`, `password = null`.
5. Log into the `web` guard, redirect to `home`.

**Login via Google:** same button on `/login`, same lookup order
(`clerk_user_id` → `email` → auto-create, since buyer accounts have no
approval gate — there's nothing "wrong" about a first-time Google login on
the login page).

The existing `StoreUserRegistrationRequest` / password path is not
modified. The Google button is additive markup plus one new route on each
page.

## Seller flow (`/sellers`, `/seller/register`, `/seller` panel login)

**Registration entry (`/sellers` landing + `/seller/register` form):** both
get a "Sign up with Google" button. Clicking it runs the Clerk flow, then
lands on `/seller/register` with `name` and `email` pre-filled and
read-only, and the `password` field removed from the form for this path.
The seller still fills in company name, contact person, phone, business
address, GST number, and uploads documents — `StoreSellerRegistrationRequest`
keeps every field it has today except `password` becomes conditionally
required (absent when a verified Clerk identity is present in the request).

**On submit:** creates the `Seller` row with `clerk_user_id` set and
`password = null`. Google already verified the email, so this path skips
`pending_email_verification` entirely and sets `status =
pending_admin_approval` directly — no activation email sent. The
email/password path is unchanged: still `pending_email_verification` →
activation email → `pending_admin_approval` on link click.

**`/seller` Filament panel login:** gets its own "Sign in with Google"
button, added via a custom Filament login page
(`->login(CustomSellerLogin::class)` extending `Filament\Pages\Auth\Login`)
that renders the button and posts to a plain (non-Filament) callback route.
That route verifies the Clerk token, looks up `Seller` by `clerk_user_id`:

- Found and `isApproved()` → log into the `seller` guard, redirect into the
  panel. Same `canAccessPanel()` gate as today — Clerk doesn't bypass
  approval.
- Not found → show an error directing them to `/seller/register` first.
- Found but not approved → same "pending approval" messaging the
  email/password path already shows.

## Edge cases handled

- **Existing password account, later uses Google with the same email**
  (buyer or seller): linked by email match, not duplicated, not rejected.
- **Clerk-linked seller who somehow also has a password** (e.g. an admin
  reset it manually): both paths keep working — `clerk_user_id` presence
  doesn't disable password login, it just means the seller *also* has a
  password-less path in.
- **Unapproved seller tries Google login on the panel:** blocked the same
  way an unapproved seller is blocked today.

## Rate limiting and bot protection

New endpoints get the same `throttle:6,1` already applied to
`/register`/`/login`/`/seller/register`. No reCAPTCHA on the Clerk path —
Google's OAuth consent flow is the bot gate; reCAPTCHA stays on the
password path only, unchanged.

## New routes

```
POST /auth/clerk/buyer            — buyer register/login callback (web guard)
POST /auth/clerk/seller/register  — seller registration identity callback
POST /auth/clerk/seller/login     — seller panel login callback (seller guard)
```

## Environment

New vars: `CLERK_PUBLISHABLE_KEY`, `CLERK_SECRET_KEY`, `CLERK_FRONTEND_API`.
Added to `.env.example` (blank) and documented in `CLAUDE.md`'s local-dev
section. `firebase/php-jwt` added to `composer.json`.

## Testing

Feature tests (SQLite, as usual — see CLAUDE.md testing notes), with JWT
verification stubbed rather than hitting real Clerk:

- Buyer register via Google: creates `User`, logs into `web` guard.
- Buyer login via Google matching an existing password account by email:
  backfills `clerk_user_id`, logs in, doesn't duplicate the row.
- Seller register via Google: form pre-fill, `password` not required, lands
  in `pending_admin_approval` with no activation email sent.
- Seller panel login via Google: succeeds only when `clerk_user_id` matches
  an approved seller; fails with the right message when unmatched or
  unapproved.
- Existing password-based register/login/seller-register tests continue to
  pass unmodified — nothing about that path changes.

## Rollout

New feature branch off `master`. Small commits, tests passing at each one,
per this repo's existing convention.

## Out of scope

- Apple Sign-In.
- Full Clerk session replacement of any Laravel guard.
- Any change to the existing email/password flows for buyers, sellers, or
  staff.
- Clerk for the `/admin` staff panel.
