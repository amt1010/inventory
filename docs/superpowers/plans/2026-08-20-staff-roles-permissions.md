# Staff Roles & Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace hardcoded role-name checks in every Policy with a real spatie `Permission`-backed matrix, so an admin can create custom staff roles (via a new `RoleResource`) and staff logins (via a new `StaffResource`) without a developer editing 7 Policy files per new role.

**Architecture:** Activate spatie/laravel-permission's already-installed but unused `permissions` table. Seed 21 permissions (`{area}.{tier}` for 7 areas × read/write/full) plus the exact permission sets that reproduce today's 3 hardcoded roles' behavior. Rewrite every Policy method to check permissions instead of role names. Add two new Filament Resources (`RoleResource`, `StaffResource`) whose own authorization stays hardcoded `admin`-only — a deliberate, permanent exception so a role can never grant itself more power. Add a forced-password-change flow (migration column + middleware + plain Blade route) for staff logins created via `StaffResource`.

**Tech Stack:** Laravel 11, Filament v3.2, spatie/laravel-permission ^6.25, PHPUnit/SQLite (test env), Livewire testing helpers.

**Spec:** `docs/superpowers/specs/2026-08-19-staff-roles-permissions-design.md`

## Global Constraints

- All new `Permission`/`Role` records use `guard_name => 'staff'` — spatie has no global guard config in this project (`config/permission.php` has no `guards` key); the guard comes from `Staff::$guard_name = 'staff'` and must be passed explicitly when creating `Permission`/`Role` rows, exactly like the existing `RoleSeeder` already does for roles.
- A role holds **at most one tier permission per area** — enforced structurally by `RoleResource`'s form (one `Select` per area, not a multi-select), not by a `full ⇒ write ⇒ read` implication chain anywhere in code.
- `RolePolicy` and `StaffPolicy` are **permanently hardcoded** to `hasRole('admin')` on every method, regardless of what the permission matrix grants any custom role — this is a deliberate anti-privilege-escalation exception, never converted to permission checks.
- `QuoteRequestPolicy::create()` stays hardcoded `false` for every role including `full` — RFQs are buyer-submitted, never staff-created. Do not change this in any task below.
- No self-service "forgot password" flow for staff — no `config('auth.passwords')` broker changes, ever, in this plan.
- No role hierarchy/inheritance helper, no per-record permissions, no staff deactivation/suspension — all explicitly out of scope per the spec.
- No payment/checkout code — not applicable to this feature, but stated per CLAUDE.md as a standing project rule.
- Run `php artisan test` (not `--filter`) at least once after the last task to confirm zero regressions across the whole suite before considering the plan done.

---

## File Structure

New files:
- `database/migrations/2026_08_20_000001_add_must_change_password_to_staff_table.php`
- `app/Policies/RolePolicy.php`
- `app/Policies/StaffPolicy.php`
- `app/Filament/Resources/RoleResource.php` + `app/Filament/Resources/RoleResource/Pages/{ListRoles,CreateRole,EditRole}.php`
- `app/Filament/Resources/StaffResource.php` + `app/Filament/Resources/StaffResource/Pages/{ListStaff,CreateStaff,EditStaff}.php`
- `app/Mail/StaffInvitation.php` + `resources/views/emails/staff-invitation.blade.php`
- `app/Http/Middleware/EnsureStaffPasswordIsCurrent.php`
- `app/Http/Controllers/StaffPasswordController.php` + `resources/views/staff/change-password.blade.php`
- `tests/Feature/{RoleSeederTest,RolePolicyTest,StaffPolicyTest,RoleResourceTest,StaffResourceTest,ForcedPasswordChangeTest}.php`
- `tests/Unit/Mail/StaffInvitationFailedTest.php`

Modified files:
- `app/Models/Staff.php` — `canAccessPanel()` fix, `must_change_password` fillable + cast
- `database/seeders/RoleSeeder.php` — permission seeding + matrix sync
- `app/Policies/{Category,Product,Seller,QuoteRequest,Page,NavItem,Setting}Policy.php` — role checks → permission checks
- `app/Providers/Filament/AdminPanelProvider.php` — new middleware in `authMiddleware`
- `routes/web.php` — new `auth:staff` change-password routes
- `tests/Feature/StaffPanelAccessTest.php` — update to match the spec's new coarse-gate behavior

---

## Task 1: `must_change_password` column + Staff model support

**Files:**
- Create: `database/migrations/2026_08_20_000001_add_must_change_password_to_staff_table.php`
- Modify: `app/Models/Staff.php`
- Test: `tests/Feature/StaffMustChangePasswordColumnTest.php`

**Interfaces:**
- Produces: `staff.must_change_password` boolean column, default `false`; `Staff::$fillable` includes `'must_change_password'`; `Staff` casts it to boolean. Later tasks (Task 15, Task 16) create/update `Staff` rows with this attribute via mass assignment.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMustChangePasswordColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_must_change_password_defaults_to_false_and_is_mass_assignable(): void
    {
        $staff = Staff::factory()->create();

        $this->assertFalse($staff->must_change_password);

        $staff2 = Staff::factory()->create(['must_change_password' => true]);

        $this->assertTrue($staff2->fresh()->must_change_password);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StaffMustChangePasswordColumnTest`
Expected: FAIL — `must_change_password` is not a mass-assignable attribute / column does not exist.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
```

- [ ] **Step 4: Update the Staff model**

In `app/Models/Staff.php`, change:

```php
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];
```

to:

```php
    protected $fillable = ['name', 'email', 'password', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'must_change_password' => 'boolean',
    ];
```

- [ ] **Step 5: Run the new migration against the dev DB, then run tests**

Run: `php artisan migrate` (applies only the new migration — never `migrate:fresh`)
Run: `php artisan test --filter=StaffMustChangePasswordColumnTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_20_000001_add_must_change_password_to_staff_table.php app/Models/Staff.php tests/Feature/StaffMustChangePasswordColumnTest.php
git commit -m "feat: add must_change_password column to staff"
```

---

## Task 2: Seed the 21-permission matrix and sync it to the 3 existing roles

**Files:**
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/RoleSeederTest.php`

**Interfaces:**
- Produces: 21 `Permission` rows named `{area}.{tier}` (guard `staff`) for areas `categories, products, sellers, quote_requests, pages, nav_items, settings` × tiers `read, write, full`. Roles `admin`, `content_editor`, `sales` end up with exactly the permission sets in the table below. Tasks 4–10 (Policy rewrites) depend on these permissions existing so the existing Policy test suite keeps passing unmodified.

Migration table (from the spec):

| Area | admin | content_editor | sales |
|---|---|---|---|
| categories | full | full | read |
| products | full | write | read |
| sellers | full | none | none |
| quote_requests | full | none | write |
| pages | full | full | read |
| nav_items | full | full | read |
| settings | full | none | none |

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_21_staff_guard_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(21, Permission::where('guard_name', 'staff')->count());
    }

    public function test_admin_role_gets_full_permission_in_every_area(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('admin', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.full', 'nav_items.full', 'pages.full', 'products.full',
            'quote_requests.full', 'sellers.full', 'settings.full',
        ], $permissions);
    }

    public function test_content_editor_role_matches_the_migration_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('content_editor', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.full', 'nav_items.full', 'pages.full', 'products.write',
        ], $permissions);
    }

    public function test_sales_role_matches_the_migration_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('sales', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.read', 'nav_items.read', 'pages.read', 'products.read', 'quote_requests.write',
        ], $permissions);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RoleSeederTest`
Expected: FAIL — 0 permissions created, roles have no permissions.

- [ ] **Step 3: Rewrite RoleSeeder**

Replace the full contents of `database/seeders/RoleSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    private const AREAS = ['categories', 'products', 'sellers', 'quote_requests', 'pages', 'nav_items', 'settings'];

    private const TIERS = ['read', 'write', 'full'];

    private const ROLE_MATRIX = [
        'admin' => [
            'categories' => 'full', 'products' => 'full', 'sellers' => 'full',
            'quote_requests' => 'full', 'pages' => 'full', 'nav_items' => 'full', 'settings' => 'full',
        ],
        'content_editor' => [
            'categories' => 'full', 'products' => 'write', 'sellers' => null,
            'quote_requests' => null, 'pages' => 'full', 'nav_items' => 'full', 'settings' => null,
        ],
        'sales' => [
            'categories' => 'read', 'products' => 'read', 'sellers' => null,
            'quote_requests' => 'write', 'pages' => 'read', 'nav_items' => 'read', 'settings' => null,
        ],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $area) {
            foreach (self::TIERS as $tier) {
                Permission::firstOrCreate(['name' => "{$area}.{$tier}", 'guard_name' => 'staff']);
            }
        }

        foreach (self::ROLE_MATRIX as $roleName => $areaTiers) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'staff']);

            $permissions = [];
            foreach ($areaTiers as $area => $tier) {
                if ($tier !== null) {
                    $permissions[] = "{$area}.{$tier}";
                }
            }

            $role->syncPermissions($permissions);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RoleSeederTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RoleSeeder.php tests/Feature/RoleSeederTest.php
git commit -m "feat: seed the 21-permission staff access matrix"
```

---

## Task 3: Fix `Staff::canAccessPanel()` to a coarse gate

**Files:**
- Modify: `app/Models/Staff.php`
- Modify: `tests/Feature/StaffPanelAccessTest.php`

**Interfaces:**
- Consumes: `Database\Seeders\RoleSeeder` (Task 2) for role/permission fixtures in the test.
- Produces: `Staff::canAccessPanel(Panel $panel): bool` returns `$panel->getId() === 'admin'` only — no role check. This is an intentional, spec-mandated behavior change: a staff member with zero roles or a brand-new custom role can now reach `/admin` (each Resource's own Policy still governs what they see/do there).

- [ ] **Step 1: Update the test to the new expected behavior**

Replace the full contents of `tests/Feature/StaffPanelAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_staff_member_with_the_admin_role_can_access_the_admin_panel(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');

        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_staff_member_with_zero_roles_can_still_access_the_admin_panel(): void
    {
        $staff = Staff::factory()->create();

        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_staff_member_with_a_brand_new_custom_role_can_access_the_admin_panel(): void
    {
        Role::firstOrCreate(['name' => 'content_creator', 'guard_name' => 'staff']);

        $staff = Staff::factory()->create();
        $staff->assignRole('content_creator');

        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StaffPanelAccessTest`
Expected: FAIL — `test_a_staff_member_with_zero_roles_can_still_access_the_admin_panel` and the custom-role test both fail against the current hardcoded role-list check.

- [ ] **Step 3: Fix the model**

In `app/Models/Staff.php`, change:

```php
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasAnyRole(['admin', 'content_editor', 'sales']);
    }
```

to:

```php
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=StaffPanelAccessTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Staff.php tests/Feature/StaffPanelAccessTest.php
git commit -m "fix: let any authenticated staff member reach the admin panel"
```

---

## Task 4: Rewrite CategoryPolicy to check permissions

**Files:**
- Modify: `app/Policies/CategoryPolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`categories.read`, `categories.write`, `categories.full`).
- Produces: no interface change — method signatures are unchanged, only their bodies check permissions instead of role names. `tests/Feature/CategoryPolicyTest.php` (existing, unmodified) is the regression check.

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=CategoryPolicyTest`
Expected: PASS (this is the baseline — it must still pass after the rewrite, unmodified)

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/CategoryPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Staff;

class CategoryPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['categories.read', 'categories.write', 'categories.full']);
    }

    public function view(Staff $staff, Category $category): bool
    {
        return $staff->hasAnyPermission(['categories.read', 'categories.write', 'categories.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['categories.write', 'categories.full']);
    }

    public function update(Staff $staff, Category $category): bool
    {
        return $staff->hasAnyPermission(['categories.write', 'categories.full']);
    }

    public function delete(Staff $staff, Category $category): bool
    {
        return $staff->hasPermissionTo('categories.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=CategoryPolicyTest`
Expected: PASS, unmodified test file. If it fails, the migration matrix in Task 2 doesn't actually match today's behavior — fix the matrix, not this test.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/CategoryPolicy.php
git commit -m "refactor: CategoryPolicy checks permissions instead of role names"
```

---

## Task 5: Rewrite ProductPolicy to check permissions

**Files:**
- Modify: `app/Policies/ProductPolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`products.read`, `products.write`, `products.full`).
- Produces: no interface change. Regression checks: `tests/Feature/ProductPricingPolicyTest.php` (existing, unmodified) covers `setPrice`/`approve`; other Product-touching tests (`SellerProductResourceTest`, `ProductResourceDehydrationSecurityTest`, etc.) exercise `create`/`update`/`delete` indirectly.

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=ProductPricingPolicyTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/ProductPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Staff;

class ProductPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['products.read', 'products.write', 'products.full']);
    }

    public function view(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyPermission(['products.read', 'products.write', 'products.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['products.write', 'products.full']);
    }

    public function update(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyPermission(['products.write', 'products.full']);
    }

    public function delete(Staff $staff, Product $product): bool
    {
        return $staff->hasPermissionTo('products.full');
    }

    public function setPrice(Staff $staff): bool
    {
        return $staff->hasPermissionTo('products.full');
    }

    public function approve(Staff $staff): bool
    {
        return $staff->hasPermissionTo('products.full');
    }
}
```

- [ ] **Step 3: Run the regression test again, plus the wider Product suite**

Run: `php artisan test --filter=ProductPricingPolicyTest`
Run: `php artisan test --filter=ProductResourceDehydrationSecurityTest`
Expected: PASS on both, unmodified test files.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/ProductPolicy.php
git commit -m "refactor: ProductPolicy checks permissions instead of role names"
```

---

## Task 6: Rewrite SellerPolicy to check permissions

**Files:**
- Modify: `app/Policies/SellerPolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`sellers.read`, `sellers.write`, `sellers.full`).
- Produces: no interface change. Regression check: `tests/Feature/SellerPolicyTest.php` (existing, unmodified).

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=SellerPolicyTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/SellerPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\Staff;

class SellerPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['sellers.read', 'sellers.write', 'sellers.full']);
    }

    public function view(Staff $staff, Seller $seller): bool
    {
        return $staff->hasAnyPermission(['sellers.read', 'sellers.write', 'sellers.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['sellers.write', 'sellers.full']);
    }

    public function update(Staff $staff, Seller $seller): bool
    {
        return $staff->hasAnyPermission(['sellers.write', 'sellers.full']);
    }

    public function delete(Staff $staff, Seller $seller): bool
    {
        return $staff->hasPermissionTo('sellers.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=SellerPolicyTest`
Expected: PASS, unmodified test file.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/SellerPolicy.php
git commit -m "refactor: SellerPolicy checks permissions instead of role names"
```

---

## Task 7: Rewrite QuoteRequestPolicy to check permissions

**Files:**
- Modify: `app/Policies/QuoteRequestPolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`quote_requests.read`, `quote_requests.write`, `quote_requests.full`).
- Produces: no interface change. `create()` stays hardcoded `false` — do not touch it. Regression check: `tests/Feature/QuoteRequestPolicyTest.php` (existing, unmodified).

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=QuoteRequestPolicyTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/QuoteRequestPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\Staff;

class QuoteRequestPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['quote_requests.read', 'quote_requests.write', 'quote_requests.full']);
    }

    public function view(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyPermission(['quote_requests.read', 'quote_requests.write', 'quote_requests.full']);
    }

    public function create(Staff $staff): bool
    {
        return false;
    }

    public function update(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyPermission(['quote_requests.write', 'quote_requests.full']);
    }

    public function delete(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasPermissionTo('quote_requests.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=QuoteRequestPolicyTest`
Expected: PASS, unmodified test file (including `test_no_one_can_create_a_quote_request_through_the_policy`).

- [ ] **Step 4: Commit**

```bash
git add app/Policies/QuoteRequestPolicy.php
git commit -m "refactor: QuoteRequestPolicy checks permissions instead of role names"
```

---

## Task 8: Rewrite PagePolicy to check permissions

**Files:**
- Modify: `app/Policies/PagePolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`pages.read`, `pages.write`, `pages.full`).
- Produces: no interface change. Regression check: `tests/Feature/PagePolicyTest.php` (existing, unmodified).

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=PagePolicyTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/PagePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\Staff;

class PagePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['pages.read', 'pages.write', 'pages.full']);
    }

    public function view(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyPermission(['pages.read', 'pages.write', 'pages.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['pages.write', 'pages.full']);
    }

    public function update(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyPermission(['pages.write', 'pages.full']);
    }

    public function delete(Staff $staff, Page $page): bool
    {
        return $staff->hasPermissionTo('pages.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=PagePolicyTest`
Expected: PASS, unmodified test file.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/PagePolicy.php
git commit -m "refactor: PagePolicy checks permissions instead of role names"
```

---

## Task 9: Rewrite NavItemPolicy to check permissions

**Files:**
- Modify: `app/Policies/NavItemPolicy.php`

**Interfaces:**
- Consumes: permissions seeded in Task 2 (`nav_items.read`, `nav_items.write`, `nav_items.full`).
- Produces: no interface change. Regression check: `tests/Feature/NavItemPolicyTest.php` (existing, unmodified).

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=NavItemPolicyTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/NavItemPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\NavItem;
use App\Models\Staff;

class NavItemPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['nav_items.read', 'nav_items.write', 'nav_items.full']);
    }

    public function view(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyPermission(['nav_items.read', 'nav_items.write', 'nav_items.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['nav_items.write', 'nav_items.full']);
    }

    public function update(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyPermission(['nav_items.write', 'nav_items.full']);
    }

    public function delete(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasPermissionTo('nav_items.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=NavItemPolicyTest`
Expected: PASS, unmodified test file.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/NavItemPolicy.php
git commit -m "refactor: NavItemPolicy checks permissions instead of role names"
```

---

## Task 10: Rewrite SettingPolicy to check permissions

**Files:**
- Modify: `app/Policies/SettingPolicy.php`

**Interfaces:**
- Consumes: permission seeded in Task 2 (`settings.full` — `settings.read`/`settings.write` exist as permission rows but no seeded role ever holds them, matching the matrix).
- Produces: no interface change. Regression check: `tests/Feature/SettingsTest.php` (existing, unmodified) — specifically `test_an_admin_can_access_the_settings_page` and `test_a_content_editor_cannot_access_the_settings_page`.

- [ ] **Step 1: Confirm the existing regression test currently passes (baseline)**

Run: `php artisan test --filter=SettingsTest`
Expected: PASS

- [ ] **Step 2: Rewrite the Policy**

Replace the full contents of `app/Policies/SettingPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\Staff;

class SettingPolicy
{
    public function manage(Staff $staff, ?Setting $setting = null): bool
    {
        return $staff->hasPermissionTo('settings.full');
    }
}
```

- [ ] **Step 3: Run the regression test again**

Run: `php artisan test --filter=SettingsTest`
Expected: PASS, unmodified test file.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/SettingPolicy.php
git commit -m "refactor: SettingPolicy checks a permission instead of a role name"
```

---

## Task 11: `RolePolicy` — hardcoded admin-only

**Files:**
- Create: `app/Policies/RolePolicy.php`
- Test: `tests/Feature/RolePolicyTest.php`

**Interfaces:**
- Consumes: `Spatie\Permission\Models\Role` (the model `RoleResource`, Task 13, manages). Laravel's policy auto-discovery resolves this from the model's class basename (`Role` → `App\Policies\RolePolicy`), the same convention already used for every other `App\Models\*` ↔ `App\Policies\*Policy` pair in this codebase — no explicit registration needed.
- Produces: `viewAny`, `view`, `create`, `update`, `delete` — all `hasRole('admin')`, unconditionally. This is the permanent exception described in Global Constraints — never converted to permission checks by a future task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_manage_roles_regardless_of_the_permission_matrix(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $role = Role::findByName('sales', 'staff');

        $this->assertTrue($admin->can('viewAny', Role::class));
        $this->assertTrue($admin->can('create', Role::class));
        $this->assertTrue($admin->can('update', $role));
        $this->assertTrue($admin->can('delete', $role));

        $this->assertFalse($editor->can('viewAny', Role::class));
        $this->assertFalse($editor->can('create', Role::class));
        $this->assertFalse($editor->can('update', $role));
        $this->assertFalse($editor->can('delete', $role));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RolePolicyTest`
Expected: FAIL — no policy registered for `Spatie\Permission\Models\Role`, `can()` calls return `false` for everyone including admin.

- [ ] **Step 3: Create the Policy**

```php
<?php

namespace App\Policies;

use App\Models\Staff;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RolePolicyTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Policies/RolePolicy.php tests/Feature/RolePolicyTest.php
git commit -m "feat: add hardcoded admin-only RolePolicy"
```

---

## Task 12: `StaffPolicy` — hardcoded admin-only

**Files:**
- Create: `app/Policies/StaffPolicy.php`
- Test: `tests/Feature/StaffPolicyTest.php`

**Interfaces:**
- Consumes: `App\Models\Staff` — both the actor and the managed record are this same model (the policy manages staff *logins*).
- Produces: `viewAny`, `view`, `create`, `update`, `delete` — all `hasRole('admin')`, unconditionally, mirroring `RolePolicy` (Task 11).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_manage_staff_logins_regardless_of_the_permission_matrix(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $target = Staff::factory()->create();

        $this->assertTrue($admin->can('viewAny', Staff::class));
        $this->assertTrue($admin->can('create', Staff::class));
        $this->assertTrue($admin->can('update', $target));
        $this->assertTrue($admin->can('delete', $target));

        $this->assertFalse($editor->can('viewAny', Staff::class));
        $this->assertFalse($editor->can('create', Staff::class));
        $this->assertFalse($editor->can('update', $target));
        $this->assertFalse($editor->can('delete', $target));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StaffPolicyTest`
Expected: FAIL — no policy registered for `App\Models\Staff`, `can()` returns `false` for everyone.

- [ ] **Step 3: Create the Policy**

```php
<?php

namespace App\Policies;

use App\Models\Staff;

class StaffPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=StaffPolicyTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Policies/StaffPolicy.php tests/Feature/StaffPolicyTest.php
git commit -m "feat: add hardcoded admin-only StaffPolicy"
```

---

## Task 13: `RoleResource` — create/edit roles with a per-area tier picker

**Files:**
- Create: `app/Filament/Resources/RoleResource.php`
- Create: `app/Filament/Resources/RoleResource/Pages/ListRoles.php`
- Create: `app/Filament/Resources/RoleResource/Pages/CreateRole.php`
- Create: `app/Filament/Resources/RoleResource/Pages/EditRole.php`
- Test: `tests/Feature/RoleResourceTest.php`

**Interfaces:**
- Consumes: `RolePolicy` (Task 11) for authorization (auto-resolved by Filament); the 21 permissions seeded in Task 2.
- Produces: `RoleResource::AREAS` (array, area key ⇒ label), `RoleResource::TIERS` (array, tier key ⇒ label), `RoleResource::permissionsFromFormData(array $data): array`, `RoleResource::tiersFromRecord(Role $record): array`, `RoleResource::summarize(Role $record): string` — all `public static`, used by the three Page classes below and available to any later task that needs to read/write a role's tier state.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_creating_a_role_with_chosen_tiers_attaches_exactly_the_right_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'field_manager',
                'tier_categories' => 'read',
                'tier_products' => 'write',
                'tier_sellers' => 'none',
                'tier_quote_requests' => 'none',
                'tier_pages' => 'none',
                'tier_nav_items' => 'none',
                'tier_settings' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'field_manager')->where('guard_name', 'staff')->firstOrFail();

        $this->assertSame(
            ['categories.read', 'products.write'],
            $role->permissions->pluck('name')->sort()->values()->all()
        );
    }

    public function test_changing_a_tier_and_resaving_updates_instead_of_accumulating_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $role = Role::create(['name' => 'field_manager', 'guard_name' => 'staff']);
        $role->syncPermissions(['categories.read']);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'name' => 'field_manager',
                'tier_categories' => 'full',
                'tier_products' => 'none',
                'tier_sellers' => 'none',
                'tier_quote_requests' => 'none',
                'tier_pages' => 'none',
                'tier_nav_items' => 'none',
                'tier_settings' => 'none',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['categories.full'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_deleting_a_role_still_assigned_to_staff_is_rejected(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staffMember = Staff::factory()->create();
        $staffMember->assignRole('sales');

        $role = Role::findByName('sales', 'staff');

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->callAction('delete');

        $this->assertNotNull($role->fresh());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RoleResourceTest`
Expected: FAIL — `RoleResource` and its pages don't exist yet.

- [ ] **Step 3: Create `RoleResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 8;

    public const AREAS = [
        'categories' => 'Categories',
        'products' => 'Products',
        'sellers' => 'Sellers',
        'quote_requests' => 'Quote Requests',
        'pages' => 'Pages',
        'nav_items' => 'Nav Items',
        'settings' => 'Settings',
    ];

    public const TIERS = [
        'none' => 'None',
        'read' => 'Read',
        'write' => 'Write',
        'full' => 'Full',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->rule(fn ($record) => Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'staff'))
                    ->ignore($record?->id)),
            Select::make('tier_categories')->label('Categories')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_products')->label('Products')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_sellers')->label('Sellers')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_quote_requests')->label('Quote Requests')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_pages')->label('Pages')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_nav_items')->label('Nav Items')->options(self::TIERS)->default('none')->required(),
            Select::make('tier_settings')->label('Settings')->options(self::TIERS)->default('none')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('access')
                    ->label('Access')
                    ->state(fn (Role $record) => self::summarize($record)),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function permissionsFromFormData(array $data): array
    {
        $permissions = [];

        foreach (array_keys(self::AREAS) as $area) {
            $tier = $data["tier_{$area}"] ?? 'none';

            if ($tier !== 'none') {
                $permissions[] = "{$area}.{$tier}";
            }
        }

        return $permissions;
    }

    public static function tiersFromRecord(Role $record): array
    {
        $names = $record->permissions->pluck('name');
        $tiers = [];

        foreach (array_keys(self::AREAS) as $area) {
            $tiers["tier_{$area}"] = 'none';

            foreach (['full', 'write', 'read'] as $tier) {
                if ($names->contains("{$area}.{$tier}")) {
                    $tiers["tier_{$area}"] = $tier;
                    break;
                }
            }
        }

        return $tiers;
    }

    public static function summarize(Role $record): string
    {
        $tiers = self::tiersFromRecord($record);
        $parts = [];

        foreach (self::AREAS as $area => $label) {
            if ($tiers["tier_{$area}"] !== 'none') {
                $parts[] = "{$label}: ".self::TIERS[$tiers["tier_{$area}"]];
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }
}
```

- [ ] **Step 4: Create the Pages**

`app/Filament/Resources/RoleResource/Pages/ListRoles.php`:

```php
<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

`app/Filament/Resources/RoleResource/Pages/CreateRole.php`:

```php
<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $permissions = RoleResource::permissionsFromFormData($data);

        $record = Role::create([
            'name' => $data['name'],
            'guard_name' => 'staff',
        ]);

        $record->syncPermissions($permissions);

        return $record;
    }
}
```

`app/Filament/Resources/RoleResource/Pages/EditRole.php`:

```php
<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Staff;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (Role $record) {
                    if (Staff::role($record->name)->exists()) {
                        Notification::make()
                            ->title('Cannot delete a role assigned to staff')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, RoleResource::tiersFromRecord($this->record));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $permissions = RoleResource::permissionsFromFormData($data);

        $record->update(['name' => $data['name']]);
        $record->syncPermissions($permissions);

        return $record;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=RoleResourceTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/RoleResource.php app/Filament/Resources/RoleResource tests/Feature/RoleResourceTest.php
git commit -m "feat: add RoleResource for managing the staff permission matrix"
```

---

## Task 14: `StaffInvitation` mailable

**Files:**
- Create: `app/Mail/StaffInvitation.php`
- Create: `resources/views/emails/staff-invitation.blade.php`
- Test: `tests/Unit/Mail/StaffInvitationFailedTest.php`

**Interfaces:**
- Consumes: `App\Models\Staff $staff`, `string $temporaryPassword`, `string $loginUrl` — constructor-promoted, following the same shape as the existing `App\Mail\SellerApproved`.
- Produces: `new StaffInvitation($staff, $temporaryPassword, $loginUrl)` — used by `CreateStaff` and `EditStaff` (Task 15).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Mail;

use App\Mail\StaffInvitation;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StaffInvitationFailedTest extends TestCase
{
    public function test_it_logs_the_staff_id_and_exception_message_but_never_the_password(): void
    {
        Log::spy();
        $staff = new Staff();
        $staff->id = 7;

        (new StaffInvitation($staff, 'super-secret-temp-pw', 'https://example.test/admin/login'))
            ->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send staff invitation email.',
            \Mockery::on(function (array $context) {
                $serialized = json_encode($context);

                return $context['staff_id'] === 7
                    && $context['exception'] === 'smtp down'
                    && ! str_contains($serialized, 'super-secret-temp-pw');
            })
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StaffInvitationFailedTest`
Expected: FAIL — `App\Mail\StaffInvitation` class does not exist.

- [ ] **Step 3: Create the Mailable**

```php
<?php

namespace App\Mail;

use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StaffInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Staff $staff, public string $temporaryPassword, public string $loginUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your admin panel login');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invitation', with: [
            'staff' => $this->staff,
            'temporaryPassword' => $this->temporaryPassword,
            'loginUrl' => $this->loginUrl,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send staff invitation email.', [
            'staff_id' => $this->staff->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Create the view**

```blade
<h1>Welcome to the admin panel</h1>
<p>An account has been created for you, {{ $staff->name }}.</p>
<p>Log in at <a href="{{ $loginUrl }}">{{ $loginUrl }}</a> using this temporary password:</p>
<p><strong>{{ $temporaryPassword }}</strong></p>
<p>You'll be asked to set a new password the first time you log in.</p>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=StaffInvitationFailedTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/StaffInvitation.php resources/views/emails/staff-invitation.blade.php tests/Unit/Mail/StaffInvitationFailedTest.php
git commit -m "feat: add StaffInvitation mailable"
```

---

## Task 15: `StaffResource` — create staff logins and reset passwords

**Files:**
- Create: `app/Filament/Resources/StaffResource.php`
- Create: `app/Filament/Resources/StaffResource/Pages/ListStaff.php`
- Create: `app/Filament/Resources/StaffResource/Pages/CreateStaff.php`
- Create: `app/Filament/Resources/StaffResource/Pages/EditStaff.php`
- Test: `tests/Feature/StaffResourceTest.php`

**Interfaces:**
- Consumes: `StaffPolicy` (Task 12) for authorization; `App\Mail\StaffInvitation` (Task 14); `must_change_password` fillable/cast (Task 1); `Spatie\Permission\Models\Role` (via `Role::where('guard_name', 'staff')`).
- Produces: creating a staff login generates a random 16-char temp password (`Str::password(16)`), hashes it, sets `must_change_password = true`, assigns the selected roles via `syncRoles()`, and queues `StaffInvitation`. The edit page's `resetPassword` header action repeats the password-generation/flag/mail steps on an existing record.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\StaffResource\Pages\CreateStaff;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class StaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_creating_a_staff_login_hashes_a_temp_password_flags_forced_change_assigns_roles_and_queues_the_invitation(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'new-editor@example.test',
                'roles' => ['content_editor'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = Staff::where('email', 'new-editor@example.test')->firstOrFail();

        $this->assertTrue($staff->must_change_password);
        $this->assertTrue($staff->hasRole('content_editor'));
        $this->assertMatchesRegularExpression('/^\$2y\$/', $staff->password);

        Mail::assertQueued(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->staff->is($staff));
    }

    public function test_resetting_a_password_reflags_forced_change_and_resends_the_invitation(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staff = Staff::factory()->create(['must_change_password' => false]);
        $staff->assignRole('sales');
        $originalPassword = $staff->password;

        Livewire::test(EditStaff::class, ['record' => $staff->id])
            ->callAction('resetPassword');

        $staff->refresh();

        $this->assertTrue($staff->must_change_password);
        $this->assertNotSame($originalPassword, $staff->password);

        Mail::assertQueued(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->staff->is($staff));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=StaffResourceTest`
Expected: FAIL — `StaffResource` and its pages don't exist yet.

- [ ] **Step 3: Create `StaffResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\Staff;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')
                ->required()
                ->email()
                ->rule(fn ($record) => Rule::unique('staff', 'email')->ignore($record?->id)),
            Select::make('roles')
                ->options(fn () => Role::where('guard_name', 'staff')->pluck('name', 'name'))
                ->multiple()
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('roles.name')->badge(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the Pages**

`app/Filament/Resources/StaffResource/Pages/ListStaff.php`:

```php
<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

`app/Filament/Resources/StaffResource/Pages/CreateStaff.php`:

```php
<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $temporaryPassword = Str::password(16);
        $roles = $data['roles'] ?? [];

        $record = Staff::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $record->syncRoles($roles);

        Mail::to($record->email)->queue(new StaffInvitation(
            $record,
            $temporaryPassword,
            Filament::getPanel('admin')->getLoginUrl(),
        ));

        return $record;
    }
}
```

`app/Filament/Resources/StaffResource/Pages/EditStaff.php`:

```php
<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetPassword')
                ->label('Reset Password')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (Staff $record) {
                    $temporaryPassword = Str::password(16);

                    $record->update([
                        'password' => Hash::make($temporaryPassword),
                        'must_change_password' => true,
                    ]);

                    Mail::to($record->email)->queue(new StaffInvitation(
                        $record,
                        $temporaryPassword,
                        Filament::getPanel('admin')->getLoginUrl(),
                    ));

                    Notification::make()
                        ->title('Temporary password sent')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles->pluck('name')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $record->syncRoles($data['roles'] ?? []);

        return $record;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=StaffResourceTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/StaffResource.php app/Filament/Resources/StaffResource tests/Feature/StaffResourceTest.php
git commit -m "feat: add StaffResource for creating staff logins and resetting passwords"
```

---

## Task 16: Forced password change on first login

**Files:**
- Create: `app/Http/Middleware/EnsureStaffPasswordIsCurrent.php`
- Create: `app/Http/Controllers/StaffPasswordController.php`
- Create: `resources/views/staff/change-password.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Feature/ForcedPasswordChangeTest.php`

**Interfaces:**
- Consumes: `must_change_password` (Task 1); the `auth:staff` middleware alias already used in `routes/web.php`.
- Produces: route `admin.change-password` (GET) and `admin.change-password.update` (POST), both outside Filament's own panel routing (registered in `routes/web.php`, protected only by `auth:staff` — deliberately not wrapped by `EnsureStaffPasswordIsCurrent`, so the redirect loop can't trap a staff member on the very page meant to clear the flag).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_staff_member_who_must_change_password_is_redirected_from_any_admin_route(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin');

        $response->assertRedirect(route('admin.change-password'));
    }

    public function test_a_staff_member_who_must_change_password_can_reach_the_change_password_route_itself(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get(route('admin.change-password'));

        $response->assertOk();
    }

    public function test_submitting_a_valid_new_password_clears_the_flag_and_allows_normal_access(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->post(route('admin.change-password.update'), [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertFalse($staff->fresh()->must_change_password);

        $this->actingAs($staff->fresh(), 'staff')->get('/admin')->assertOk();
    }

    public function test_a_staff_member_with_the_flag_already_false_is_never_redirected(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => false]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ForcedPasswordChangeTest`
Expected: FAIL — routes don't exist (404s), middleware isn't wired up.

- [ ] **Step 3: Create the middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');

        if ($staff?->must_change_password && ! $request->routeIs('admin.change-password')) {
            return redirect()->route('admin.change-password');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffPasswordController extends Controller
{
    public function edit(): View
    {
        return view('staff.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $staff = $request->user('staff');

        $staff->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return redirect('/admin');
    }
}
```

- [ ] **Step 5: Create the view**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Set a new password</title>
</head>
<body>
    <h1>Set a new password</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.change-password.update') }}">
        @csrf
        <label for="password">New password</label>
        <input type="password" name="password" id="password" required>

        <label for="password_confirmation">Confirm new password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required>

        <button type="submit">Set password</button>
    </form>
</body>
</html>
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, add the `use` import near the other controller imports:

```php
use App\Http\Controllers\StaffPasswordController;
```

Then change:

```php
Route::middleware('auth:staff')->group(function () {
    Route::get('/preview/product/{product}', [PreviewController::class, 'product'])->name('staff.preview.product');
    Route::get('/preview/category/{category}', [PreviewController::class, 'category'])->name('staff.preview.category');
});
```

to:

```php
Route::middleware('auth:staff')->group(function () {
    Route::get('/preview/product/{product}', [PreviewController::class, 'product'])->name('staff.preview.product');
    Route::get('/preview/category/{category}', [PreviewController::class, 'category'])->name('staff.preview.category');
    Route::get('/admin/change-password', [StaffPasswordController::class, 'edit'])->name('admin.change-password');
    Route::post('/admin/change-password', [StaffPasswordController::class, 'update'])->name('admin.change-password.update');
});
```

- [ ] **Step 7: Wire the middleware into the admin panel**

In `app/Providers/Filament/AdminPanelProvider.php`, add the import:

```php
use App\Http\Middleware\EnsureStaffPasswordIsCurrent;
```

Then change:

```php
            ->authMiddleware([
                Authenticate::class,
            ]);
```

to:

```php
            ->authMiddleware([
                Authenticate::class,
                EnsureStaffPasswordIsCurrent::class,
            ]);
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=ForcedPasswordChangeTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Middleware/EnsureStaffPasswordIsCurrent.php app/Http/Controllers/StaffPasswordController.php resources/views/staff/change-password.blade.php routes/web.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/ForcedPasswordChangeTest.php
git commit -m "feat: force a password change on a staff member's first login"
```

---

## Final Verification

- [ ] **Run the entire suite once more, not filtered**

Run: `php artisan test`
Expected: PASS, zero failures, zero regressions across every existing test file touched or left alone by this plan.
