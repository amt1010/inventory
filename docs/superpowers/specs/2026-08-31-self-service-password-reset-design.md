# Self-Service Password Reset — Design Spec

Date: 2026-08-31
Status: Approved

## Purpose

Today, self-service "forgot password" exists only for sellers (Filament's
built-in `passwordReset()` on the seller panel). Staff have no self-service
path — only another admin can reset a staff member's password
(`StaffResource`'s admin-triggered reset). Buyers have no password reset at
all; `CLAUDE.md` currently documents this as deliberate ("no email
verification, no password reset" for the low-friction buyer guard).

This spec adds self-service reset for staff and buyers, and adds a missing
safety check to the seller flow that already exists. `CLAUDE.md`'s buyer
section is updated to describe the new behavior instead of the old
restriction.

## Eligibility rule

Reset is offered based on whether an account has a usable local password —
`password !== null` — not on whether `clerk_user_id` is set. These aren't
the same thing: a buyer who registered with email/password and *later*
linked Google keeps their original password (`ClerkBuyerAuthController`
finds-or-attaches by email, it doesn't clear `password`), so
`clerk_user_id !== null` alone would wrongly exclude them. Sellers always
get `password = null` when they register via Clerk
(`Seller\RegistrationController`), so for sellers the two checks happen to
coincide, but the code checks `password !== null` uniformly across all
three guards rather than relying on that coincidence.

Staff have no Clerk registration path at all, so every staff account is
unconditionally eligible.

## Staff (`/admin`) — new

Mirrors the seller panel's existing setup:

- New migration: `create_staff_password_reset_tokens_table` (same shape as
  the existing `seller_password_reset_tokens` migration).
- New `config/auth.php` broker: `passwords.staff` → `provider: staff,
  table: staff_password_reset_tokens, expire: 60, throttle: 60`.
- `AdminPanelProvider`: add `->passwordReset()->authPasswordBroker('staff')`,
  matching `SellerPanelProvider`'s existing lines.

**`must_change_password` interaction**: `StaffResource`'s admin-triggered
reset sets `must_change_password = true` because the admin picked a
temporary password for someone else. A self-service reset means the staff
member chose their own new password directly — it shouldn't then force
*another* change on next login. A new listener on
`Illuminate\Auth\Events\PasswordReset`, registered in
`AppServiceProvider::boot()`, checks if the event's `user` is a `Staff`
instance and, if so, sets `must_change_password = false` and saves.

## Sellers (`/seller`) — existing flow, new guard

The existing `passwordReset()` flow has no check today: a Clerk-only
seller (`password === null`) can request and complete a reset, silently
creating a password-based login alongside their Clerk one — not what "not
applicable for Clerk accounts" means.

Fix: override the reset-link-request step (Filament's `RequestPasswordReset`
page, or a custom broker call ahead of it) to look up the `Seller` by
submitted email first. If found and `password === null`, skip calling
`Password::sendResetLink()` entirely — but render the exact same "if that
email is registered, we've sent a link" confirmation regardless of whether
the account exists, is Clerk-only, or the email doesn't match anything.
Never reveal via response differences which case occurred (standard
anti-enumeration practice).

Per the earlier email-templates spec, the seller reset email also becomes
an entry in that system (`seller_password_reset`) instead of Filament's
default notification-based email — see
`2026-08-31-email-template-admin-editing-design.md`.

## Buyers (`/login`) — new

No Breeze/Fortify scaffolding exists in this app (registration/login are
hand-rolled controllers), so this is a small custom flow rather than a
package feature:

- `config/auth.php` already has an unused `passwords.users` broker
  pointing at the `password_reset_tokens` table (present since the
  original `users` migration) — no new migration needed.
- New `App\Http\Controllers\PasswordResetController` on the `web` guard,
  four actions: `showRequestForm` (GET), `sendResetLink` (POST),
  `showResetForm` (GET, signed token in the URL), `reset` (POST).
  Uses Laravel's `Password` facade against the `users` broker, matching
  the hand-rolled style of `RegistrationController`/`SessionController`
  rather than pulling in a scaffolding package for one flow.
- Same Clerk-only guard and same generic confirmation message as sellers:
  look up `User` by email, skip the actual `Password::sendResetLink()`
  call if `password === null`, but always show the same confirmation
  screen.
- Two new Bootstrap-styled views (`auth/forgot-password.blade.php`,
  `auth/reset-password.blade.php`) matching `auth/login.blade.php`'s
  existing look, reusing the same required-field asterisk/neon-red
  validation styling already applied to that form.
- A "Forgot password?" link added next to the Log In button on
  `auth/login.blade.php`.
- Reset-link email is `App\Mail\BuyerPasswordReset` (`buyer_password_reset`
  key in the email-templates system), not Laravel's default
  `ResetPassword` notification — consistent with every other transactional
  email in this app.
- Rate limiting: same `throttle:6,1` already applied to
  `/register`/`/login` (see the Clerk auth spec's precedent), applied to
  both the request-link and submit-new-password routes.

## New routes

```
GET  /forgot-password              — buyer: request form
POST /forgot-password               — buyer: send reset link
GET  /reset-password/{token}        — buyer: set new password form
POST /reset-password                — buyer: submit new password
```

Staff and seller routes are Filament's own (`/admin/password-reset/...`,
`/seller/password-reset/...`), auto-registered by `->passwordReset()` —
nothing to hand-write.

## CLAUDE.md update

The buyer-account paragraph currently reads: "...no email verification, no
password reset — used only to view past quote requests and favorites."
Replace with a description of the eligibility rule above: password reset
is available to buyers who registered with email/password; accounts that
signed up via Clerk/Google with no local password are not eligible (same
rule for sellers).

## Testing

- Staff: self-service reset completes and logs must-change-password false
  afterward; admin-triggered reset still sets it true and is unaffected;
  full round trip (request link → submit new password → login with it).
- Seller: Clerk-only seller's reset request returns the generic
  confirmation but sends no mail (`Mail::assertNothingSent` or equivalent);
  password-based seller's request sends `SellerPasswordReset`
  successfully; full round trip for the password case.
- Buyer: same two cases as seller (Clerk-only silently no-ops with generic
  response; password-based buyer gets a real link and can complete the
  round trip); rate limiting on both new routes; existing register/login
  tests continue to pass unmodified.

## Rollout

Depends on `2026-08-31-email-template-admin-editing-design.md` being
implemented first (or alongside) for the three new reset emails to have
somewhere to live — sequence: email-templates migration/seeder/renderer
first, then this spec's migrations, routes, controllers, and listener.
Small commits, tests passing at each one.

## Out of scope

- Any change to the Clerk sign-in flows themselves (covered by
  `2026-08-30-clerk-google-auth-design.md`).
- Offering Clerk-only accounts a "set a password for the first time" path
  as part of this feature — reset only, not password creation from
  scratch for a Clerk-only account.
- Rate-limit or throttle changes to the existing seller/staff Filament
  reset pages beyond adding the Clerk-only guard.
