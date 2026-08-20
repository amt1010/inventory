# Staff Roles & Permissions — Design Spec

Date: 2026-08-19
Status: Approved

## Purpose

Today the admin panel has exactly 3 hardcoded staff roles (`admin`,
`content_editor`, `sales`), created once by `RoleSeeder` and never touched
again. Every authorization boundary — all 7 Policy classes under
`app/Policies/` — checks these role NAMES directly (`hasRole('admin')`,
`hasAnyRole([...])`). There is no Filament UI to create a staff login at
all; new staff members can only be added via a seeder or `tinker`.

As the business grows, the admin (GitHub issue #34) needs to onboard new
role types — content creators, product leads, field managers — each with
a different slice of admin-panel access, without a developer hardcoding a
new role name and editing 7 Policy files every time. This spec adds:

1. A `RoleResource` letting an admin create a role and configure
   None/Read/Write/Full access per resource area.
2. A `StaffResource` letting an admin create a staff login (email + role
   assignment), which emails the new staff member a temp password.
3. A forced password change on that staff member's first login.

## Approach

`spatie/laravel-permission` is already this project's declared RBAC tool
(see CLAUDE.md) and its `permissions` table already exists via the
package's own migration — but is completely unused; every Policy checks
role names instead of permission records. This spec activates that
dormant mechanism rather than building a parallel one, which would
duplicate what spatie already provides.

## Data Model: the permission matrix

7 resource areas, each with 3 tiers, seeded as 21 spatie `Permission`
records (guard `staff`), named `{area}.{tier}`:

`categories`, `products`, `sellers`, `quote_requests`, `pages`,
`nav_items`, `settings` — × `read`, `write`, `full`.

- **Read**: `viewAny`/`view` only.
- **Write**: adds `create`/`update`.
- **Full**: adds `delete`, plus the resource's own sensitive actions —
  `Product::setPrice`/`approve`, `Seller` approve/reject, `Setting::manage`.

A role holds at most one tier permission per area (None = no permission
for that area at all). `RoleResource`'s form enforces this by syncing
exactly one (or zero) permission per area on save, not by a `full ⇒
write ⇒ read` implication chain in the Policies — each Policy method
checks the specific permission(s) that grant it, e.g.:

```php
public function update(Staff $staff, Product $product): bool
{
    return $staff->hasAnyPermission(['products.write', 'products.full']);
}
```

**"Staff & Roles" management is not part of this matrix.** Creating staff
logins, creating roles, and editing permissions stays hardcoded
admin-only (`StaffPolicy`, `RolePolicy`, both modeled on the existing
`SettingPolicy`), regardless of what any custom role is granted. This is
a deliberate, permanent exception — not a default that changes as the
matrix evolves — because letting a role grant itself more power (or
grant another role admin-equivalent access) is a privilege-escalation
hole with no legitimate use case here.

## Migrating the 3 existing roles

`RoleSeeder` changes from creating bare roles to also syncing permissions
that reproduce **exactly** what each role can do today, so existing staff
logins see no behavior change:

| Area | admin | content_editor | sales |
|---|---|---|---|
| categories | full | full | read |
| products | full | write | read |
| sellers | full | none | none |
| quote_requests | full | none | write |
| pages | full | full | read |
| nav_items | full | full | read |
| settings | full | none | none |

(Cross-checked against the current `ViewAny`/`create`/`update`/`delete`
grants in each of the 7 existing Policy files. Note `products` is the
one area where `content_editor`'s current access is `write`, not `full`
— `ProductPolicy` restricts `delete`/`setPrice`/`approve` to `admin`
only, unlike `CategoryPolicy`/`NavItemPolicy`/`PagePolicy`, which all
grant `content_editor` delete too.)

`QuoteRequestPolicy::create()` stays hardcoded `false` for every role,
including `full` — RFQs are buyer-submitted, never staff-created; this is
a business invariant, not a permission-tier question, and predates this
spec.

## `Staff::canAccessPanel()` fix

Today: `hasAnyRole(['admin', 'content_editor', 'sales'])` — a hardcoded
list that would silently lock a brand-new custom role (e.g.
"content_creator") out of `/admin` entirely, even after being granted
permissions via `RoleResource`. Changes to:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $panel->getId() === 'admin';
}
```

Any authenticated staff member may enter the panel; each Resource's own
`viewAny` Policy check (via the permission matrix above) still governs
what they actually see in the nav and can act on — this matches
Filament's standard coarse-gate/fine-grained-policy split, already used
elsewhere in this codebase.

## `RoleResource` (new, admin-only)

- **List**: name, a summary of granted areas (e.g. "Products: Full,
  Categories: Write, ...").
- **Create/Edit form**: `TextInput('name')` + one `Select` per resource
  area (`None`/`Read`/`Write`/`Full`, default `None`). On save, computes
  the exact permission set implied by each area's selected tier and
  calls `$role->syncPermissions([...])` — this is the only place that
  translates a tier choice into concrete permission records.
- Deleting a role fails loudly (validation error, not silent) if any
  `Staff` row still has it assigned — mirrors how `CategoryPolicy`-style
  resources in this codebase avoid orphaning references.

## `StaffResource` (new, admin-only)

- **List**: name, email, roles (badges).
- **Create form**: `TextInput('name')`, `TextInput('email')` (unique),
  `Select('roles')->multiple()` (spatie natively supports multiple roles
  per user; this just exposes it — a staff member could be both
  `content_editor` and `sales`, for instance).
- **On create** (not a plain form save — a dedicated action, since it has
  side effects beyond the DB row):
  1. Generate a random temp password (`Str::password(16)`).
  2. Create the `Staff` row with that password (hashed) and
     `must_change_password = true`.
  3. Assign the selected roles.
  4. Queue `App\Mail\StaffInvitation` (new Mailable, following the
     existing `SellerApproved`-style convention: `ShouldQueue`,
     constructor-promoted properties, `envelope()`/`content()`, a
     `failed()` handler that logs the staff ID only — never the
     password) to the new staff member, containing the admin-panel login
     URL and the temp password.
- **Edit form**: name, email, roles only — no password field. A separate
  header `Action::make('resetPassword')` (admin-only, confirmation
  modal) regenerates a temp password, re-sets `must_change_password =
  true`, and re-sends `StaffInvitation` — reusing the same Mailable
  rather than introducing a second one.

## Forced password change on first login

- **Migration**: adds `must_change_password` (boolean, default `false`)
  to `staff`.
- **New middleware** `EnsureStaffPasswordIsCurrent`, added to
  `AdminPanelProvider`'s `->authMiddleware([...])` stack (after
  `Authenticate::class`). On every request: if the authenticated staff
  member has `must_change_password = true` and the current route isn't
  already the change-password route, redirect there.
- **New route + controller** `GET/POST /admin/change-password`
  (`StaffPasswordController`, plain Blade form — not a Filament Resource
  page, to keep it trivially excludable from the middleware above by
  route name) protected by `auth:staff` but explicitly NOT by
  `EnsureStaffPasswordIsCurrent`. On submit: validates the new password
  (confirmed, Laravel's default password rules), updates it, and clears
  `must_change_password`.
- A staff member who already has `must_change_password = false` (every
  existing seeded/admin-created row, migrated with a default of `false`)
  is completely unaffected — this only fires for logins created via the
  new `StaffResource` flow.

## Explicitly out of scope (YAGNI)

- **Self-service "forgot password" for staff.** No `config('auth.passwords')`
  broker changes. The issue asks for admin-set temp passwords with a
  forced first-login change, not a self-service reset-by-email flow —
  and per CLAUDE.md, the buyer `web` guard deliberately has no password
  reset either, so staff being the only guard with *any* password-recovery
  mechanism would be a new precedent this spec doesn't need to set.
- **Role hierarchy / inheritance** (e.g. "Full implies Write implies
  Read" as a computed relationship). Each tier is its own concrete
  permission; `RoleResource` computes the right set at save time instead.
- **Per-record permissions** (e.g. "can edit only Products in category
  X"). The issue asks for access "across the board" per resource type,
  not scoped subsets.
- **Deactivating/suspending a staff login** without deleting it. Not
  mentioned in the issue; can be a follow-up if needed.

## Testing

- **Permission seeding**: `RoleSeeder` creates all 21 permissions and the
  3 existing roles end up with exactly the matrix above (assert via
  `Role::findByName(...)->permissions->pluck('name')`).
- **Regression**: every existing Policy test in the suite continues to
  pass unmodified — the 3 existing roles' effective access must not
  change. (If any Policy test needs updating to keep passing, that is a
  sign the migrated permission matrix doesn't actually match today's
  behavior and the mapping table above needs correcting, not the test.)
- **`canAccessPanel`**: a staff member with a brand-new custom role (no
  permissions at all) can reach `/admin` but sees no resources in the
  nav; a staff member with zero roles can still reach `/admin` (coarse
  gate only) but every resource is hidden.
- **`RoleResource`**: creating a role with a chosen tier per area results
  in exactly the right permissions attached; changing a tier and
  re-saving updates (not accumulates) permissions; deleting a role that's
  still assigned to a `Staff` row is rejected.
- **`StaffResource`**: creating a staff login generates a hashed
  password, sets `must_change_password = true`, assigns the selected
  roles, and queues `StaffInvitation` to the entered email; the "Reset
  Password" action re-flags `must_change_password` and re-sends the
  mail.
- **Forced password change**: a staff member with `must_change_password
  = true` hitting any `/admin/*` route is redirected to
  `/admin/change-password`; submitting a valid new password there clears
  the flag and allows normal access afterward; a staff member with the
  flag already `false` is never redirected.
- **`StaffInvitation` mailable**: renders the login URL and temp
  password; `failed()` logs only the staff ID, never the password
  (assert via a mocked `Log::error` call's context array).
