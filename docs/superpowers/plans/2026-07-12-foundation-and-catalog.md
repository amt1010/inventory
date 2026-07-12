# Foundation & Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the rebuilt Laravel 11 foundation for the B2B marketplace — category tree, products (owned by sellers, priced by Admin), the Filament `/admin` CMS panel with role-based access, and the public catalog browsing + search pages — replacing the legacy Laravel 9 inventory app.

**Architecture:** Single Laravel 11 app. Public catalog is server-rendered Blade + Bootstrap, resolved by one controller walking a self-referencing `categories` tree. Internal CMS is a Filament v3 panel at `/admin` on its own `staff` guard, with RBAC via `spatie/laravel-permission`. This plan builds the data model and Admin/Content Editor capability only — the Seller Portal, RFQ system, and content-page builder are separate follow-on plans per the design spec.

**Tech Stack:** Laravel 11, PHP 8.2, MySQL, Filament v3, spatie/laravel-permission, Laravel Scout (database driver), Blade + Bootstrap (CDN), PHPUnit.

## Global Constraints

- Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md` — read it before deviating from this plan.
- No payment/checkout code anywhere in this system.
- Currency is INR only; `price_display` is a free-text string, not structured min/max fields.
- Categories are a single self-referencing table (any depth) — never reintroduce fixed named category-level tables.
- A product can only be `published` if `price_display` is set, and only an Admin-role staff member may set `price_display` or change a product's `status`.
- Seller identity is never shown on any public page.
- Only `published` categories/products are ever reachable via public routes.
- TDD: write the failing test before the implementation, for every task that has application logic.
- Commit after every task.

---

### Task 1: Baseline commit and Laravel 11 skeleton upgrade

**Files:**
- Modify: `composer.json`, `bootstrap/app.php` (new), `bootstrap/providers.php` (new), `public/index.php`, `artisan`, `app/Providers/AppServiceProvider.php`, `routes/console.php`, `package.json`
- Modify: `database/migrations/*` (replace default Laravel migrations with the Laravel 11 default set)
- Delete: `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `app/Providers/RouteServiceProvider.php`, `app/Providers/EventServiceProvider.php`, `app/Providers/AuthServiceProvider.php`, `app/Providers/BroadcastServiceProvider.php`

**Interfaces:**
- Produces: a working Laravel 11 application skeleton that `php artisan --version` reports as Laravel Framework 11.x, ready for Task 2 onward to build on.

- [ ] **Step 1: Commit the existing legacy app as a baseline before any destructive changes**

```bash
git add -A
git commit -m "Baseline: legacy Laravel 9 inventory app before marketplace rebuild"
```

This ensures every legacy file is recoverable from git history even after later deletions.

- [ ] **Step 2: Generate a fresh Laravel 11 skeleton in a sibling temp directory**

```bash
composer create-project laravel/laravel:^11.0 /c/tmp-laravel11-skeleton --prefer-dist --no-interaction
```

Expected: a complete fresh Laravel 11 app is created at `/c/tmp-laravel11-skeleton`.

- [ ] **Step 3: Copy the framework skeleton files into the project**

```bash
cp /c/tmp-laravel11-skeleton/bootstrap/app.php /c/inventory/bootstrap/app.php
cp /c/tmp-laravel11-skeleton/bootstrap/providers.php /c/inventory/bootstrap/providers.php
cp /c/tmp-laravel11-skeleton/public/index.php /c/inventory/public/index.php
cp /c/tmp-laravel11-skeleton/artisan /c/inventory/artisan
cp /c/tmp-laravel11-skeleton/app/Providers/AppServiceProvider.php /c/inventory/app/Providers/AppServiceProvider.php
cp /c/tmp-laravel11-skeleton/routes/console.php /c/inventory/routes/console.php
cp /c/tmp-laravel11-skeleton/app/Http/Controllers/Controller.php /c/inventory/app/Http/Controllers/Controller.php
cp -f /c/tmp-laravel11-skeleton/config/*.php /c/inventory/config/
rm -f /c/inventory/config/offcr.php
rm -f /c/inventory/database/migrations/2014_10_12_000000_create_users_table.php \
      /c/inventory/database/migrations/2014_10_12_100000_create_password_resets_table.php \
      /c/inventory/database/migrations/2019_08_19_000000_create_failed_jobs_table.php \
      /c/inventory/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php \
      /c/inventory/database/migrations/2022_06_18_103739_create_add_fir_table.php
cp /c/tmp-laravel11-skeleton/database/migrations/*.php /c/inventory/database/migrations/
cp /c/tmp-laravel11-skeleton/package.json /c/inventory/package.json
cp /c/tmp-laravel11-skeleton/vite.config.js /c/inventory/vite.config.js
rm -f /c/inventory/app/Http/Kernel.php /c/inventory/app/Console/Kernel.php /c/inventory/app/Exceptions/Handler.php \
      /c/inventory/app/Providers/RouteServiceProvider.php /c/inventory/app/Providers/EventServiceProvider.php \
      /c/inventory/app/Providers/AuthServiceProvider.php /c/inventory/app/Providers/BroadcastServiceProvider.php
```

Expected: `bootstrap/app.php` now contains the Laravel 11 `Application::configure()` style bootstrap, and no Laravel 9-era Kernel/Handler files remain.

- [ ] **Step 4: Update `composer.json` to Laravel 11 versions**

Open `/c/tmp-laravel11-skeleton/composer.json` and copy its `require`/`require-dev` version constraints for `php`, `laravel/framework`, `laravel/tinker`, `phpunit/phpunit`, `nunomaduro/collision`, `fakerphp/faker`, `mockery/mockery`, `laravel/sail` into `/c/inventory/composer.json`, replacing the old ones. Keep `guzzlehttp/guzzle` and `laravel/sanctum` in `require` (update their versions to whatever the fresh skeleton specifies, or drop them if the fresh skeleton doesn't include them and re-add via `composer require guzzlehttp/guzzle laravel/sanctum` in Step 5 instead). **Remove** `laravel/ui` from `require` (not needed — Filament replaces the admin UI, and buyer-facing auth will be custom Blade forms).

- [ ] **Step 5: Install dependencies and generate the app key**

```bash
composer install
php artisan key:generate
```

Expected: no composer errors.

- [ ] **Step 6: Verify the upgrade**

```bash
php artisan --version
```

Expected output: `Laravel Framework 11.x.x`

```bash
php artisan about
```

Expected: runs without error and prints the application environment table.

- [ ] **Step 7: Clean up the temp skeleton and commit**

```bash
rm -rf /c/tmp-laravel11-skeleton
git add -A
git commit -m "Upgrade application skeleton to Laravel 11"
```

---

### Task 2: Remove legacy business code

**Files:**
- Delete: `app/Models/Addproduct.php`, `app/Models/Adminquery.php`, `app/Models/Allnews.php`, `app/Models/Employee.php`, `app/Models/Lastmenu.php`, `app/Models/Officer.php`, `app/Models/Product.php`, `app/Models/Product_image.php`, `app/Models/Submenu.php`, `app/Models/Thirdmenu.php`, `app/Models/Topmenu.php`
- Delete: `app/Http/Controllers/AddemployeeController.php`, `AddnewsController.php`, `AuthController.php`, `DropdownController.php`, `EditnewsController.php`, `EmploginController.php`, `LastmenuController.php`, `ProductController.php`, `SubmenuController.php`, `TopmenuController.php`, `ViewempController.php`, and the `Auth/` and `Home/` subdirectories under `app/Http/Controllers`
- Delete: everything under `resources/views/` except nothing is kept (all legacy Blade views are removed; new ones are created in later tasks)
- Modify: `routes/web.php` (replaced with a minimal placeholder)

**Interfaces:**
- Produces: a clean `app/` and `resources/views/` tree with no legacy inventory-app code, ready for the new models/controllers/views built in later tasks.

- [ ] **Step 1: Delete legacy models**

```bash
rm -f /c/inventory/app/Models/Addproduct.php /c/inventory/app/Models/Adminquery.php \
      /c/inventory/app/Models/Allnews.php /c/inventory/app/Models/Employee.php \
      /c/inventory/app/Models/Lastmenu.php /c/inventory/app/Models/Officer.php \
      /c/inventory/app/Models/Product.php /c/inventory/app/Models/Product_image.php \
      /c/inventory/app/Models/Submenu.php /c/inventory/app/Models/Thirdmenu.php \
      /c/inventory/app/Models/Topmenu.php
```

Note: `app/Models/User.php` is kept as-is — it's already a standard Authenticatable model and will be reused for buyer accounts in a later plan.

- [ ] **Step 2: Delete legacy controllers**

```bash
rm -f /c/inventory/app/Http/Controllers/AddemployeeController.php \
      /c/inventory/app/Http/Controllers/AddnewsController.php \
      /c/inventory/app/Http/Controllers/AuthController.php \
      /c/inventory/app/Http/Controllers/DropdownController.php \
      /c/inventory/app/Http/Controllers/EditnewsController.php \
      /c/inventory/app/Http/Controllers/EmploginController.php \
      /c/inventory/app/Http/Controllers/LastmenuController.php \
      /c/inventory/app/Http/Controllers/ProductController.php \
      /c/inventory/app/Http/Controllers/SubmenuController.php \
      /c/inventory/app/Http/Controllers/TopmenuController.php \
      /c/inventory/app/Http/Controllers/ViewempController.php
rm -rf /c/inventory/app/Http/Controllers/Auth /c/inventory/app/Http/Controllers/Home
```

- [ ] **Step 3: Delete legacy views**

```bash
rm -rf /c/inventory/resources/views
mkdir -p /c/inventory/resources/views
```

- [ ] **Step 4: Replace `routes/web.php` with a minimal placeholder**

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
```

Also create a minimal `resources/views/welcome.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    <p>Under construction.</p>
</body>
</html>
```

- [ ] **Step 5: Verify the app still boots**

```bash
php artisan route:list
```

Expected: no errors, lists the `/` route.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Remove legacy inventory-app business logic ahead of marketplace rebuild"
```

---

### Task 3: Install core packages (Filament, Spatie Permission, Scout)

**Files:**
- Modify: `composer.json`, `.env`, `.env.example`
- Create: `config/permission.php`, `config/scout.php`, `app/Providers/Filament/AdminPanelProvider.php` (generated), `database/migrations/*_create_permission_tables.php` (generated)

**Interfaces:**
- Produces: Filament, spatie/laravel-permission, and Laravel Scout installed and bootable, ready for Task 4 to configure the `staff` guard and panel.

- [ ] **Step 1: Require the packages**

```bash
composer require filament/filament:"^3.2" -W
composer require spatie/laravel-permission
composer require laravel/scout
```

Expected: composer resolves and installs all three without conflicts.

- [ ] **Step 2: Install the Filament admin panel**

```bash
php artisan filament:install --panels
```

Expected: creates `app/Providers/Filament/AdminPanelProvider.php` and registers it in `bootstrap/providers.php`.

- [ ] **Step 3: Publish the Spatie Permission config and migration**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Expected: creates `config/permission.php` and a `database/migrations/*_create_permission_tables.php` file.

- [ ] **Step 4: Publish the Scout config**

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Expected: creates `config/scout.php`.

- [ ] **Step 5: Configure Scout to use the database driver**

Add to `.env` and `.env.example`:

```
SCOUT_DRIVER=database
```

- [ ] **Step 6: Verify**

```bash
php artisan about
```

Expected: runs without error; the "Environment" section shows no missing-provider errors.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Install Filament, Spatie Permission, and Laravel Scout"
```

---

### Task 4: Staff authentication, roles, and the `/admin` panel guard

**Files:**
- Create: `database/migrations/2026_07_12_120000_create_staff_table.php`
- Create: `app/Models/Staff.php`
- Create: `database/factories/StaffFactory.php`
- Create: `database/seeders/RoleSeeder.php`, `database/seeders/StaffSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `config/auth.php`, `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Feature/StaffPanelAccessTest.php`

**Interfaces:**
- Produces: `App\Models\Staff` (fields: `name`, `email`, `password`; `HasRoles` trait; `guard_name = 'staff'`; `canAccessPanel(Panel $panel): bool`) and roles `admin`, `content_editor`, `sales` seeded on the `staff` guard. Later tasks use `auth('staff')->user()->can(...)` and `hasRole('admin')` / `hasAnyRole([...])`.
- Consumes: Filament/Spatie packages from Task 3.

- [ ] **Step 1: Write the migration**

`database/migrations/2026_07_12_120000_create_staff_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
```

- [ ] **Step 2: Write the `Staff` model**

`app/Models/Staff.php`:

```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Staff extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'staff';

    protected $table = 'staff';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasAnyRole(['admin', 'content_editor', 'sales']);
    }
}
```

- [ ] **Step 3: Add the `staff` guard and provider to `config/auth.php`**

In the `guards` array, add:

```php
'staff' => [
    'driver' => 'session',
    'provider' => 'staff',
],
```

In the `providers` array, add:

```php
'staff' => [
    'driver' => 'eloquent',
    'model' => App\Models\Staff::class,
],
```

- [ ] **Step 4: Point the Filament admin panel at the `staff` guard**

Edit `app/Providers/Filament/AdminPanelProvider.php` — in the `panel()` method's chain, add `->authGuard('staff')` immediately after `->login()`:

```php
return $panel
    ->id('admin')
    ->path('admin')
    ->login()
    ->authGuard('staff')
    // ...rest of the generated chain unchanged
```

- [ ] **Step 5: Write the `StaffFactory`**

`database/factories/StaffFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }
}
```

- [ ] **Step 6: Write the failing test**

`tests/Feature/StaffPanelAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_staff_member_without_any_role_cannot_access_the_admin_panel(): void
    {
        $staff = Staff::factory()->create();

        $this->assertFalse($staff->canAccessPanel(Filament::getPanel('admin')));
    }
}
```

- [ ] **Step 7: Run the test to verify it fails**

```bash
php artisan test --filter=StaffPanelAccessTest
```

Expected: FAIL — `RoleSeeder` doesn't exist yet.

- [ ] **Step 8: Write the seeders**

`database/seeders/RoleSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'content_editor', 'sales'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'staff']);
        }
    }
}
```

`database/seeders/StaffSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Staff::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => Hash::make('password')]
        );

        $admin->assignRole('admin');
    }
}
```

Update `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StaffSeeder::class,
        ]);
    }
}
```

- [ ] **Step 9: Run the test to verify it passes**

```bash
php artisan test --filter=StaffPanelAccessTest
```

Expected: PASS (2 tests)

- [ ] **Step 10: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/admin`, log in with `admin@example.com` / `password`. Expected: the Filament dashboard loads.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "Add staff authentication, roles, and admin panel guard"
```

---

### Task 5: Categories schema, model, and tests

**Files:**
- Create: `database/migrations/2026_07_12_120100_create_categories_table.php`
- Create: `app/Models/Category.php`
- Create: `database/factories/CategoryFactory.php`
- Test: `tests/Feature/CategoryTreeTest.php`

**Interfaces:**
- Produces: `App\Models\Category` with `parent()`, `children()`, `products()` relations and `isPublished(): bool`. Fields: `parent_id`, `name`, `slug`, `description`, `image`, `status`, `sort_order`. Later tasks (`CatalogController`, `CategoryResource`, `ProductFactory`) depend on this exact shape.

- [ ] **Step 1: Write the failing test**

`tests/Feature/CategoryTreeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_can_have_children(): void
    {
        $parent = Category::factory()->create(['slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);

        $this->assertTrue($parent->fresh()->children->contains($child));
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_sibling_categories_cannot_share_a_slug_under_the_same_parent(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CategoryTreeTest
```

Expected: FAIL — `Category` class / `categories` table doesn't exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_12_120100_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('draft'); // draft|published
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Enforces uniqueness among siblings that share a non-null parent.
            // Root-level (parent_id IS NULL) slug uniqueness is enforced at the
            // application layer in CategoryResource (Task 8), since MySQL unique
            // indexes treat NULL values as distinct from one another.
            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Write the model**

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image', 'status', 'sort_order',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
```

- [ ] **Step 5: Write the factory**

`database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraph(),
            'status' => 'published',
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate:fresh
php artisan test --filter=CategoryTreeTest
```

Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add categories self-referencing tree schema and model"
```

---

### Task 6: Sellers, seller documents, and custom attributes schema

**Files:**
- Create: `database/migrations/2026_07_12_120200_create_sellers_table.php`
- Create: `database/migrations/2026_07_12_120300_create_seller_documents_table.php`
- Create: `database/migrations/2026_07_12_120400_create_custom_attributes_table.php`
- Create: `app/Models/Seller.php`, `app/Models/SellerDocument.php`, `app/Models/CustomAttribute.php`
- Create: `database/factories/SellerFactory.php`
- Test: `tests/Feature/SellerModelTest.php`

**Interfaces:**
- Produces: `App\Models\Seller` (fields: `company_name`, `contact_person`, `phone`, `email`, `password`, `business_address`, `gst_number`, `status`, `created_by`, `rejection_reason`, `email_verified_at`, `approved_at`, `approved_by`) with `products()`, `documents()`, `customAttributes()` relations. Not yet Authenticatable — a later plan (Seller Marketplace Layer) adds login behavior on top of this table. `App\Models\CustomAttribute` is polymorphic (`attributable()`), reused by both `Seller` and `Product`.
- Consumes: `staff` table from Task 4 (for `approved_by` FK).

- [ ] **Step 1: Write the failing test**

`tests/Feature/SellerModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CustomAttribute;
use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_have_documents(): void
    {
        $seller = Seller::factory()->create();
        $document = SellerDocument::create([
            'seller_id' => $seller->id,
            'label' => 'GST Certificate',
            'file_path' => 'seller-documents/gst.pdf',
        ]);

        $this->assertTrue($seller->documents->contains($document));
    }

    public function test_a_seller_can_have_custom_attributes(): void
    {
        $seller = Seller::factory()->create();
        $seller->customAttributes()->create(['label' => 'Import License', 'value' => 'IL-12345']);

        $this->assertCount(1, $seller->fresh()->customAttributes);
        $this->assertSame('Import License', $seller->fresh()->customAttributes->first()->label);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SellerModelTest
```

Expected: FAIL — `Seller` class / `sellers` table doesn't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_12_120200_create_sellers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('business_address')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('status')->default('pending_email_verification');
            // pending_email_verification|pending_admin_approval|approved|rejected|suspended
            $table->string('created_by')->default('self'); // self|admin
            $table->text('rejection_reason')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
```

`database/migrations/2026_07_12_120300_create_seller_documents_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('file_path');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
    }
};
```

`database/migrations/2026_07_12_120400_create_custom_attributes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_attributes', function (Blueprint $table) {
            $table->id();
            $table->morphs('attributable');
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_attributes');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/Seller.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
    ];

    protected $hidden = ['password'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function customAttributes(): MorphMany
    {
        return $this->morphMany(CustomAttribute::class, 'attributable');
    }
}
```

`app/Models/SellerDocument.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerDocument extends Model
{
    protected $fillable = ['seller_id', 'label', 'file_path', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
```

`app/Models/CustomAttribute.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomAttribute extends Model
{
    protected $fillable = ['label', 'value', 'file_path', 'sort_order'];

    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: Write the factory**

`database/factories/SellerFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class SellerFactory extends Factory
{
    protected $model = Seller::class;

    public function definition(): array
    {
        return [
            'company_name' => $this->faker->company(),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'business_address' => $this->faker->address(),
            'gst_number' => strtoupper($this->faker->bothify('##???####?#?#')),
            'status' => 'approved',
            'created_by' => 'self',
        ];
    }
}
```

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate:fresh
php artisan test --filter=SellerModelTest
```

Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add sellers, seller documents, and custom attributes schema"
```

---

### Task 7: Products and product images schema, models, and the publish invariant

**Files:**
- Create: `database/migrations/2026_07_12_120500_create_products_table.php`
- Create: `database/migrations/2026_07_12_120600_create_product_images_table.php`
- Create: `app/Models/Product.php`, `app/Models/ProductImage.php`
- Create: `database/factories/ProductFactory.php`
- Test: `tests/Feature/ProductModelTest.php`

**Interfaces:**
- Produces: `App\Models\Product` (fields: `seller_id`, `category_id`, `name`, `slug`, `sku`, `short_description`, `description`, `features` (array), `applications` (array), `spec_sheet_path`, `price_display`, `status`, `rejection_reason`, `sort_order`) with `category()`, `seller()`, `images()`, `customAttributes()` relations, `isPublished(): bool`, and `publish(): bool` (returns `false` and leaves `status` unchanged if `price_display` is blank; otherwise sets `status = 'published'` and returns `true`). Uses `Laravel\Scout\Searchable`. Later tasks (`CatalogController`, `ProductResource`, search) depend on this exact shape.
- Consumes: `Category` (Task 5), `Seller` (Task 6).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_belongs_to_a_seller_and_a_category(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($product->seller->is($seller));
        $this->assertTrue($product->category->is($category));
    }

    public function test_a_product_cannot_be_published_without_a_price(): void
    {
        $product = Product::factory()->create(['price_display' => null, 'status' => 'pending_review']);

        $result = $product->publish();

        $this->assertFalse($result);
        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_a_product_with_a_price_can_be_published(): void
    {
        $product = Product::factory()->create(['price_display' => '₹1,000 – ₹1,500', 'status' => 'pending_review']);

        $result = $product->publish();

        $this->assertTrue($result);
        $this->assertSame('published', $product->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=ProductModelTest
```

Expected: FAIL — `Product` class / `products` table doesn't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_12_120500_create_products_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->json('applications')->nullable();
            $table->string('spec_sheet_path')->nullable();
            $table->string('price_display')->nullable();
            $table->string('status')->default('pending_review'); // pending_review|published|rejected|archived
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

`database/migrations/2026_07_12_120600_create_product_images_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/Product.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'seller_id', 'category_id', 'name', 'slug', 'sku', 'short_description',
        'description', 'features', 'applications', 'spec_sheet_path',
        'price_display', 'status', 'rejection_reason', 'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'applications' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function customAttributes(): MorphMany
    {
        return $this->morphMany(CustomAttribute::class, 'attributable');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publish(): bool
    {
        if (blank($this->price_display)) {
            return false;
        }

        $this->status = 'published';
        $this->save();

        return true;
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
        ];
    }
}
```

`app/Models/ProductImage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort_order', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

- [ ] **Step 5: Write the factory**

`database/factories/ProductFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'seller_id' => Seller::factory(),
            'category_id' => Category::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'features' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'applications' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'price_display' => '₹'.$this->faker->numberBetween(500, 2000).' – ₹'.$this->faker->numberBetween(2001, 5000),
            'status' => 'published',
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate:fresh
php artisan test --filter=ProductModelTest
```

Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add products and product images schema, with the publish invariant"
```

---

### Task 8: Filament CategoryResource

**Files:**
- Create: `app/Filament/Resources/CategoryResource.php`, `app/Filament/Resources/CategoryResource/Pages/ListCategories.php`, `CreateCategory.php`, `EditCategory.php` (generated)
- Test: `tests/Feature/CategoryResourceTest.php`

**Interfaces:**
- Produces: a Filament resource at `/admin/categories` usable by any staff member with the `admin` or `content_editor` role, enforcing per-parent slug uniqueness (including root-level).
- Consumes: `Category` (Task 5), `Staff`/roles (Task 4).

- [ ] **Step 1: Generate the resource**

```bash
php artisan make:filament-resource Category
```

Expected: creates `app/Filament/Resources/CategoryResource.php` and a `Pages` subdirectory.

- [ ] **Step 2: Write the failing test**

`tests/Feature/CategoryResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Models\Category;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_top_level_categories_cannot_share_a_slug(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Fiber Optic Cable Duplicate',
                'slug' => 'fiber-optic-cable',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --filter=CategoryResourceTest
```

Expected: FAIL — the generated resource's default form has no `slug` uniqueness rule yet.

- [ ] **Step 4: Replace the generated `form()` and `table()` methods**

In `app/Filament/Resources/CategoryResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('parent_id')
                ->label('Parent Category')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')
                ->required()
                ->rule(fn (callable $get, $record) => Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where('parent_id', $get('parent_id')))
                    ->ignore($record?->id)),
            RichEditor::make('description'),
            FileUpload::make('image')
                ->image()
                ->directory('categories'),
            Select::make('status')
                ->options(['draft' => 'Draft', 'published' => 'Published'])
                ->default('draft')
                ->required(),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('parent.name')->label('Parent')->placeholder('— Top level —'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --filter=CategoryResourceTest
```

Expected: PASS

- [ ] **Step 6: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/admin/categories`, log in as `admin@example.com`, create a category, confirm it appears in the list.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add Filament CategoryResource with per-parent slug uniqueness"
```

---

### Task 9: Filament ProductResource with role-gated pricing

**Files:**
- Create: `app/Filament/Resources/ProductResource.php`, `app/Filament/Resources/ProductResource/Pages/*` (generated)
- Create: `app/Policies/ProductPolicy.php` (generated then edited)
- Test: `tests/Feature/ProductPricingPolicyTest.php`

**Interfaces:**
- Produces: `App\Policies\ProductPolicy` with `setPrice(Staff $staff): bool` (true only for the `admin` role) and `approve(Staff $staff): bool` (true only for `admin`), auto-discovered by Laravel for the `Product` model. A Filament resource at `/admin/products` where the price and status fields are disabled and non-dehydrated for any staff member who fails the `setPrice` check.
- Consumes: `Product` (Task 7), `Category` (Task 5), `Seller` (Task 6), `Staff`/roles (Task 4).

- [ ] **Step 1: Generate the resource and policy**

```bash
php artisan make:filament-resource Product
php artisan make:policy ProductPolicy --model=Product
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/ProductPricingPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_the_admin_role_can_set_product_price(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($admin->can('setPrice', Product::class));
        $this->assertFalse($editor->can('setPrice', Product::class));
    }

    public function test_only_the_admin_role_can_approve_a_product(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertTrue($admin->can('approve', Product::class));
        $this->assertFalse($sales->can('approve', Product::class));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --filter=ProductPricingPolicyTest
```

Expected: FAIL — the generated `ProductPolicy` has no `setPrice`/`approve` methods yet.

- [ ] **Step 4: Write the policy**

`app/Policies/ProductPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Staff;

class ProductPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, Product $product): bool
    {
        return $staff->hasRole('admin');
    }

    public function setPrice(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function approve(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --filter=ProductPricingPolicyTest
```

Expected: PASS (2 tests)

- [ ] **Step 6: Replace the generated `form()` and `table()` methods**

In `app/Filament/Resources/ProductResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        $canSetPrice = auth('staff')->user()?->can('setPrice', Product::class) ?? false;

        return $form->schema([
            Select::make('seller_id')
                ->label('Seller')
                ->options(fn () => Seller::query()->pluck('company_name', 'id'))
                ->searchable()
                ->required(),
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => Category::query()->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')->required(),
            TextInput::make('sku')->label('SKU / Product Number'),
            TextInput::make('short_description'),
            RichEditor::make('description'),
            Repeater::make('features')->simple(TextInput::make('value')->required()),
            Repeater::make('applications')->simple(TextInput::make('value')->required()),
            FileUpload::make('spec_sheet_path')
                ->label('Specification Sheet (PDF)')
                ->directory('spec-sheets')
                ->acceptedFileTypes(['application/pdf']),
            TextInput::make('price_display')
                ->label('Price Range (INR)')
                ->placeholder('e.g. ₹1,200 – ₹1,800 per reel')
                ->disabled(! $canSetPrice)
                ->dehydrated($canSetPrice),
            Select::make('status')
                ->options([
                    'pending_review' => 'Pending Review',
                    'published' => 'Published',
                    'rejected' => 'Rejected',
                    'archived' => 'Archived',
                ])
                ->disabled(! $canSetPrice)
                ->dehydrated($canSetPrice)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('seller.company_name')->label('Seller'),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('status')->badge(),
                TextColumn::make('price_display')->label('Price'),
            ])
            ->actions([
                Action::make('publish')
                    ->visible(fn () => auth('staff')->user()?->can('approve', Product::class) ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => $record->publish()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 7: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Log into `/admin/products` as the seeded admin, create a product, confirm the price field is editable and the record can be published.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Add Filament ProductResource with role-gated pricing and approval"
```

---

### Task 10: Local catalog seed data

**Files:**
- Create: `database/seeders/CatalogSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: seeded sample data (a demo seller, a 3-level category tree `Fiber Optic Cable > Aerial > OPGW`, two published products) usable by Task 11's routing tests and for manual browsing.
- Consumes: `Category` (Task 5), `Seller` (Task 6), `Product` (Task 7).

- [ ] **Step 1: Write the seeder**

`database/seeders/CatalogSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $seller = Seller::factory()->create(['company_name' => 'Demo Supplier Co.']);

        $fiberOpticCable = Category::factory()->create([
            'name' => 'Fiber Optic Cable',
            'slug' => 'fiber-optic-cable',
            'parent_id' => null,
        ]);

        $aerial = Category::factory()->create([
            'name' => 'Aerial',
            'slug' => 'aerial',
            'parent_id' => $fiberOpticCable->id,
        ]);

        $opgw = Category::factory()->create([
            'name' => 'OPGW',
            'slug' => 'opgw',
            'parent_id' => $aerial->id,
        ]);

        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $opgw->id,
            'name' => 'CentraCore Optical Ground Wire (OPGW)',
            'slug' => 'centracore-opgw-cable',
            'status' => 'published',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);

        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $opgw->id,
            'name' => 'HexaCore Optical Ground Wire (OPGW)',
            'slug' => 'hexacore-opgw-cable',
            'status' => 'published',
            'price_display' => '₹1,500 – ₹2,100 per reel',
        ]);
    }
}
```

- [ ] **Step 2: Wire it into `DatabaseSeeder`**

`database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StaffSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
```

- [ ] **Step 3: Verify**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\Product::count();"
```

Expected: prints `2`.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Add local catalog seed data"
```

---

### Task 11: Public catalog path-resolution controller

**Files:**
- Create: `app/Http/Controllers/CatalogController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CatalogRoutingTest.php`

**Interfaces:**
- Produces: `GET /products/{path?}` resolving to either the `catalog.category` view (with `category` (nullable), `breadcrumb` (array of `Category`), `children` (Collection of `Category`), `products` (Collection of `Product`)) or the `catalog.product` view (with `product` (`Product`), `breadcrumb` (array of `Category`)), or a 404.
- Consumes: `Category` (Task 5), `Product` (Task 7).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/CatalogRoutingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_products_hub_lists_top_level_published_categories(): void
    {
        $topLevel = Category::factory()->create(['status' => 'published', 'slug' => 'fiber-optic-cable']);
        Category::factory()->create(['status' => 'draft', 'slug' => 'hidden-category']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->contains($topLevel) && $children->count() === 1);
    }

    public function test_a_nested_category_path_resolves_and_builds_a_breadcrumb(): void
    {
        $parent = Category::factory()->create(['status' => 'published', 'slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['status' => 'published', 'slug' => 'aerial', 'parent_id' => $parent->id]);

        $response = $this->get('/products/fiber-optic-cable/aerial');

        $response->assertOk();
        $response->assertViewHas('category', fn ($category) => $category->is($child));
        $response->assertViewHas('breadcrumb', fn ($breadcrumb) => array_map(fn ($c) => $c->id, $breadcrumb) === [$parent->id, $child->id]);
    }

    public function test_a_product_slug_as_the_final_segment_renders_the_product_page(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'aerial']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'slug' => 'centracore-opgw-cable',
        ]);

        $response = $this->get('/products/aerial/centracore-opgw-cable');

        $response->assertOk();
        $response->assertViewIs('catalog.product');
        $response->assertViewHas('product', fn ($p) => $p->is($product));
    }

    public function test_a_pending_review_product_is_not_reachable_publicly(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'aerial']);
        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'pending_review',
            'slug' => 'unapproved-cable',
        ]);

        $response = $this->get('/products/aerial/unapproved-cable');

        $response->assertNotFound();
    }

    public function test_an_unknown_path_returns_404(): void
    {
        $response = $this->get('/products/does-not-exist');

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=CatalogRoutingTest
```

Expected: FAIL — `CatalogController` doesn't exist and the `/products` route isn't registered.

- [ ] **Step 3: Write the controller**

`app/Http/Controllers/CatalogController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    public function show(Request $request, string $path = ''): View
    {
        $segments = array_values(array_filter(explode('/', $path)));

        $breadcrumb = [];
        $parentId = null;
        $category = null;

        foreach ($segments as $index => $segment) {
            $category = Category::query()
                ->where('parent_id', $parentId)
                ->where('slug', $segment)
                ->where('status', 'published')
                ->first();

            if ($category) {
                $breadcrumb[] = $category;
                $parentId = $category->id;

                continue;
            }

            $isLastSegment = $index === array_key_last($segments);

            if ($isLastSegment && $parentId !== null) {
                $product = Product::query()
                    ->where('category_id', $parentId)
                    ->where('slug', $segment)
                    ->where('status', 'published')
                    ->first();

                if ($product) {
                    return view('catalog.product', [
                        'product' => $product,
                        'breadcrumb' => $breadcrumb,
                    ]);
                }
            }

            abort(Response::HTTP_NOT_FOUND);
        }

        return view('catalog.category', [
            'category' => $category,
            'breadcrumb' => $breadcrumb,
            'children' => $category
                ? $category->children()->where('status', 'published')->get()
                : Category::query()->whereNull('parent_id')->where('status', 'published')->orderBy('sort_order')->get(),
            'products' => $category
                ? $category->products()->where('status', 'published')->orderBy('sort_order')->get()
                : collect(),
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

Add to `routes/web.php`:

```php
use App\Http\Controllers\CatalogController;

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');
```

- [ ] **Step 5: Create placeholder views so the tests can render**

`resources/views/catalog/category.blade.php`:

```blade
<h1>Category placeholder — replaced in Task 12</h1>
```

`resources/views/catalog/product.blade.php`:

```blade
<h1>Product placeholder — replaced in Task 12</h1>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --filter=CatalogRoutingTest
```

Expected: PASS (5 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add public catalog path-resolution controller and routing"
```

---

### Task 12: Public catalog Blade templates

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/catalog/category.blade.php`, `resources/views/catalog/product.blade.php`

**Interfaces:**
- Produces: the real category and product templates, extending a shared layout with a header/search bar, matching the AFL reference structure (breadcrumb, hero, tiles/grid, features/applications, spec sheet, related products).
- Consumes: `CatalogController` view data (Task 11).

- [ ] **Step 1: Write the base layout**

`resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <form class="d-flex ms-auto" action="{{ route('catalog.search') }}" method="GET">
                <input class="form-control me-2" type="search" name="q" placeholder="Search for item by keyword or product number" value="{{ request('q') }}">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
        </div>
    </nav>

    <main class="container py-4">
        @yield('content')
    </main>
</body>
</html>
```

- [ ] **Step 2: Write the category template**

`resources/views/catalog/category.blade.php`:

```blade
@extends('layouts.app')

@section('title', $category->name ?? 'Products')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/products') }}">Home</a></li>
            @foreach ($breadcrumb as $crumb)
                <li class="breadcrumb-item">
                    <a href="{{ url('/products/'.collect($breadcrumb)->take($loop->iteration)->pluck('slug')->implode('/')) }}">{{ $crumb->name }}</a>
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($category)
        <h1>{{ $category->name }}</h1>
        @if ($category->description)
            <div class="mb-4">{!! $category->description !!}</div>
        @endif
    @else
        <h1>Products</h1>
    @endif

    @if ($children->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($children as $child)
                <div class="col">
                    <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($child->slug)->implode('/')) }}" class="card h-100 text-decoration-none">
                        @if ($child->image)
                            <img src="{{ asset('storage/'.$child->image) }}" class="card-img-top" alt="{{ $child->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title text-dark">{{ $child->name }}</h5>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($products->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-2">
            @foreach ($products as $product)
                <div class="col">
                    <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="card h-100 text-decoration-none">
                        @if ($product->images->first())
                            <img src="{{ asset('storage/'.$product->images->first()->path) }}" class="card-img-top" alt="{{ $product->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title text-dark">{{ $product->name }}</h5>
                            <p class="card-text text-muted">{{ $product->short_description }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
```

- [ ] **Step 3: Write the product template**

`resources/views/catalog/product.blade.php`:

```blade
@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/products') }}">Home</a></li>
            @foreach ($breadcrumb as $crumb)
                <li class="breadcrumb-item">
                    <a href="{{ url('/products/'.collect($breadcrumb)->take($loop->iteration)->pluck('slug')->implode('/')) }}">{{ $crumb->name }}</a>
                </li>
            @endforeach
            <li class="breadcrumb-item active">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-6">
            @foreach ($product->images as $image)
                <img src="{{ asset('storage/'.$image->path) }}" class="img-fluid mb-2" alt="{{ $product->name }}">
            @endforeach
        </div>
        <div class="col-md-6">
            <h1>{{ $product->name }}</h1>

            @if ($product->price_display)
                <p class="fs-4 text-primary">{{ $product->price_display }}</p>
            @endif

            @if (! empty($product->features))
                <h5>Features</h5>
                <ul>
                    @foreach ($product->features as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($product->applications))
                <h5>Applications</h5>
                <ul>
                    @foreach ($product->applications as $application)
                        <li>{{ $application }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($product->spec_sheet_path)
                <a href="{{ asset('storage/'.$product->spec_sheet_path) }}" class="btn btn-outline-danger">Download Specification Sheet</a>
            @endif

            {{-- The "Get a Quote" flow is built in the RFQ / Quote System plan; this is a placeholder link until then. --}}
            <a href="#" class="btn btn-primary mt-3">Get a Quote</a>
        </div>
    </div>

    <hr class="my-4">

    <div>
        <h5>Product Description</h5>
        <div>{!! $product->description !!}</div>
    </div>

    @php
        $related = \App\Models\Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'published')
            ->limit(4)
            ->get();
    @endphp

    @if ($related->isNotEmpty())
        <h5 class="mt-4">Related Products</h5>
        <div class="row row-cols-1 row-cols-md-4 g-4">
            @foreach ($related as $relatedProduct)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">{{ $relatedProduct->name }}</h6>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
```

- [ ] **Step 4: Run the full catalog test suite**

```bash
php artisan test --filter=CatalogRoutingTest
```

Expected: PASS (5 tests) — the placeholder views from Task 11 are now replaced but the same assertions hold.

- [ ] **Step 5: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/products`, then drill into `fiber-optic-cable/aerial/opgw`, then into `centracore-opgw-cable`. Expected: breadcrumb, tiles, and product detail all render.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Add public catalog category and product Blade templates"
```

---

### Task 13: Catalog search via Laravel Scout

**Files:**
- Create: `app/Http/Controllers/SearchController.php`
- Create: `resources/views/catalog/search.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CatalogSearchTest.php`

**Interfaces:**
- Produces: `GET /search?q=...` rendering matching, published products by name/SKU.
- Consumes: `Product` with `Searchable` trait (Task 7), Scout `database` driver (Task 3).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/CatalogSearchTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_published_products_by_name(): void
    {
        Product::factory()->create(['name' => 'CentraCore OPGW Cable', 'status' => 'published']);
        Product::factory()->create(['name' => 'Unrelated Widget', 'status' => 'published']);

        $response = $this->get('/search?q=OPGW');

        $response->assertOk();
        $response->assertSee('CentraCore OPGW Cable');
        $response->assertDontSee('Unrelated Widget');
    }

    public function test_search_excludes_non_published_products(): void
    {
        Product::factory()->create(['name' => 'Hidden Cable', 'status' => 'pending_review']);

        $response = $this->get('/search?q=Hidden');

        $response->assertOk();
        $response->assertDontSee('Hidden Cable');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=CatalogSearchTest
```

Expected: FAIL — `/search` route doesn't exist.

- [ ] **Step 3: Write the controller**

`app/Http/Controllers/SearchController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = (string) $request->query('q', '');

        $results = $query !== ''
            ? Product::search($query)->where('status', 'published')->get()
            : collect();

        return view('catalog.search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

Add to `routes/web.php` (before the `/products/{path?}` route, so `/search` isn't swallowed by the wildcard):

```php
use App\Http\Controllers\SearchController;

Route::get('/search', SearchController::class)->name('catalog.search');
```

- [ ] **Step 5: Write the view**

`resources/views/catalog/search.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Search Results')

@section('content')
    <h1>Search Results for "{{ $query }}"</h1>

    @if ($results->isEmpty())
        <p>No products found.</p>
    @else
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($results as $product)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $product->name }}</h5>
                            <p class="card-text text-muted">{{ $product->short_description }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --filter=CatalogSearchTest
```

Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add catalog search via Laravel Scout database driver"
```

---

### Task 14: CLAUDE.md and local developer workflow documentation

**Files:**
- Create: `CLAUDE.md`

**Interfaces:**
- Produces: project-context documentation for future Claude Code sessions (or any engineer) — architecture map, tech stack, local dev workflow commands, and coding conventions specific to this codebase.

- [ ] **Step 1: Write `CLAUDE.md`**

`CLAUDE.md`:

```markdown
# CLAUDE.md

This file gives Claude Code (and any other engineer) the context needed to work in
this repository without re-deriving it from scratch.

## What this project is

A B2B marketplace platform (Alibaba-style), styled after AFL's public catalog site.
Three actor types:

- **Buyers** — public visitors who browse the catalog and submit "Request a Quote"
  (RFQ) enquiries. No checkout, no payments anywhere in this system.
- **Sellers** — registered suppliers who list their own products/surplus inventory
  via the `/seller` Filament panel (built in a later plan). Never see buyer contact
  details or interact with buyers directly.
- **Staff** (Admin / Content Editor / Sales) — manage the catalog, price and approve
  seller listings, and handle quote requests via the `/admin` Filament panel.

Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md` —
read that before making architectural changes. Implementation plans (this codebase
is being built phase by phase) live in `docs/superpowers/plans/`.

## Tech stack

- Laravel 11, PHP 8.2, MySQL
- Filament v3 for the internal CMS — two panels: `/admin` (staff guard, built) and
  `/seller` (seller guard, built in a later phase)
- `spatie/laravel-permission` for role-based access control (roles: `admin`,
  `content_editor`, `sales`, all on the `staff` guard)
- Laravel Scout (`database` driver) for catalog search — deliberately abstracted so
  swapping to Meilisearch/Typesense later is a driver change, not a rewrite
- Blade + Bootstrap (via CDN) for the public-facing catalog — no SPA framework

## Architecture map

- `app/Models/Category.php` — self-referencing tree (`parent_id`), any depth. A
  category with children renders as a hub; one without renders its products.
- `app/Models/Product.php` — belongs to exactly one `Seller` and one leaf
  `Category`. `status` moves through `pending_review → published` (or
  `rejected`/`archived`). `price_display` is a free-text field settable only by the
  Admin role — see `App\Policies\ProductPolicy::setPrice()`. Never set
  `status = 'published'` directly; call `Product::publish()`, which enforces that
  `price_display` is set first.
- `app/Http/Controllers/CatalogController.php` — resolves the wildcard route
  `/products/{path?}` by walking the category tree segment by segment; renders
  either the category template or the product template. This single controller and
  its two templates cover every depth of the catalog (Products hub, Category,
  Sub-Category, Product-Family, etc.) — there is deliberately no per-depth template.
- `app/Filament/Resources/` — staff-facing CRUD screens. Every resource has a
  matching `App\Policies\*Policy` enforcing role boundaries server-side (not just
  hidden nav items).
- Seller identity is never rendered on any public page — the catalog is fully
  platform-branded.

## Local development workflow

First-time setup:

```
composer install
npm install
cp .env.example .env   # if .env doesn't already exist
php artisan key:generate
```

Configure `.env` for your local MySQL instance (`DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`), then:

```
php artisan migrate:fresh --seed
```

This seeds the three staff roles, a login-ready admin account
(`admin@example.com` / `password` — change or remove before any real deployment),
and sample catalog data.

Day-to-day commands:

- `php artisan serve` — run the app locally
- `php artisan test` — run the full test suite (do this before every commit)
- `php artisan test --filter=SomeTestName` — run a single test while iterating
- `php artisan tinker` — inspect data interactively
- `php artisan migrate:fresh --seed` — reset the local DB to a known state
- `/admin` — staff CMS (Admin / Content Editor / Sales)
- `/seller` — seller portal (added in a later phase, not yet built)
- `/products` — public catalog root
- `/search?q=...` — catalog search

## Conventions and best practices for working in this codebase

- **Test-first.** Every new behavior gets a failing test before the implementation.
  Feature tests live in `tests/Feature`.
- **RBAC lives in Policies, not just Filament form visibility.** Any field or action
  that must be role-gated needs both a Policy method (the actual authorization
  boundary) and, in Filament, `->disabled()` **and** `->dehydrated()` tied to that
  same policy check — `disabled()` alone is cosmetic and can be bypassed.
- **Categories are one self-referencing table, not fixed named levels.** Never
  reintroduce hardcoded category-depth tables (the legacy app's
  `Topmenu`/`Submenu`/`Thirdmenu`/`Lastmenu` pattern) — that's exactly what this
  rebuild replaced.
- **No payment/checkout code, ever, per the spec.** Final pricing is negotiated
  off-platform after the RFQ conversation. If a task seems to need a payment
  gateway, stop and re-check the spec — it almost certainly means the requirement
  was misread.
- **Seller identity stays internal.** Never add seller name/company to a
  public-facing view or API response — `products.seller_id` is for internal use
  (Admin/Sales, and the seller's own portal) only.
- **A product cannot be `published` without `price_display` set** — this is
  enforced in `Product::publish()`, not re-implemented ad hoc elsewhere.
- Commit frequently, in small units — one logical change per commit, tests passing
  at each commit.
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "Add CLAUDE.md project documentation and developer workflow"
```

---

### Task 15: End-to-end verification

**Files:** none (verification only)

**Interfaces:** none — this task confirms Tasks 1–14 integrate correctly.

- [ ] **Step 1: Full reset and seed**

```bash
php artisan migrate:fresh --seed
```

Expected: no errors.

- [ ] **Step 2: Full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 3: Manual smoke test**

```bash
php artisan serve
```

- Visit `http://127.0.0.1:8000/admin`, log in as `admin@example.com` / `password`. Confirm the dashboard loads.
- In `/admin/categories`, confirm the seeded `Fiber Optic Cable > Aerial > OPGW` tree is visible.
- In `/admin/products`, confirm the two seeded products are visible with their `price_display` values, and that the `status` and price fields are editable (logged in as admin).
- Visit `http://127.0.0.1:8000/products`, drill into `fiber-optic-cable/aerial/opgw`, then into `centracore-opgw-cable`. Confirm breadcrumb, price, features, and description render.
- Visit `http://127.0.0.1:8000/search?q=OPGW`. Confirm both seeded products appear.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "Foundation & Catalog plan complete: verified end-to-end"
```
