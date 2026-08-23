# Category & Product Bulk Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Admin bulk-create a category tree and, optionally, one product per row from a single Excel/CSV upload — nothing is ever auto-published — plus a new required Product field (Raw Material / Finished Good) and a dedicated Audit Logs page recording who ran every bulk import (this one and the existing seller one).

**Architecture:** A plain PHP `CategoryChainResolver` service resolves or creates a category tree top-down and is unit-tested independently of Filament. `CategoryProductImporter extends Filament\Actions\Imports\Importer`, using the resolver inside `resolveRecord()`, returning `null` to cleanly skip a row (Filament's own supported "skip" mechanism). A new `AuditLog` model is populated in two places: at import *dispatch* time (an `Import::created` hook, where `auth('staff')->user()` is reliably available) and at import *completion* time (`getCompletedNotificationBody()`, updating the same row by a stored `filament_import_id` — no `auth()` needed for that half).

**Tech Stack:** Laravel 11, Filament v3 (`filament/actions` `Importer`/`ImportAction`, already installed and already has `Import::polymorphicUserRelationship()` enabled), MySQL (dev/prod), SQLite (tests).

**Spec:** `docs/superpowers/specs/2026-08-23-category-product-bulk-import-design.md`

## Global Constraints

- Every new behavior gets a failing test first (`php artisan test --filter=...`), per this repo's test-first convention.
- Migrations must be additive; verify with `php artisan migrate` against the real dev database, never `migrate:fresh`, per `CLAUDE.md`.
- Commit after each task, small units, tests passing at each commit.
- Run `php artisan test` (the full suite) before each commit to confirm no regressions, in addition to the task's own new tests.
- The literal placeholder string is `Category::PLACEHOLDER = 'TO BE ADDED'` (this exact casing) — use the constant everywhere, never a hardcoded string.
- This whole feature lives on branch `feature/issue-33-category-product-bulk-import` — do not push or merge; the user is testing manually in local first.

---

### Task 1: `products`/`categories` schema changes

**Files:**
- Create: `database/migrations/2026_08_23_100100_add_bulk_import_fields_to_products_and_categories.php`
- Modify: `app/Models/Product.php`
- Modify: `app/Models/Category.php`
- Modify: `database/factories/ProductFactory.php`
- Test: `tests/Feature/CategoryProductBulkImportSchemaTest.php`

**Interfaces:**
- Produces: `Category::PLACEHOLDER` (string constant `'TO BE ADDED'`), used by Task 2 (`CategoryChainResolver`). `products.material_type` (not-nullable, `raw_material`|`finished_good`), `products.seller_id` (now nullable), `products.created_by`/`categories.created_by` (nullable strings) — all used by Task 2 and Task 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductBulkImportSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_created_with_no_seller(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['seller_id' => null, 'category_id' => $category->id]);

        $this->assertNull($product->fresh()->seller_id);
    }

    public function test_material_type_is_not_nullable_and_the_factory_supplies_one(): void
    {
        $product = Product::factory()->create();

        $this->assertContains($product->fresh()->material_type, ['raw_material', 'finished_good']);
    }

    public function test_products_and_categories_created_by_default_to_null(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();

        $this->assertNull($product->fresh()->created_by);
        $this->assertNull($category->fresh()->created_by);
    }

    public function test_the_category_placeholder_constant_is_the_literal_string_to_be_added(): void
    {
        $this->assertSame('TO BE ADDED', Category::PLACEHOLDER);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CategoryProductBulkImportSchemaTest`
Expected: FAIL — `products.material_type`/`created_by`/`categories.created_by` columns don't exist, `Category::PLACEHOLDER` undefined, and `seller_id` may currently reject `null` depending on the DB driver's FK enforcement.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('material_type')->default('raw_material')->after('category_id');
            $table->string('created_by')->nullable()->after('sort_order');
        });

        // SQLite (used in tests) can't alter a column's nullability or drop/
        // re-add a foreign key in place the way MySQL can; recreate the FK
        // as nullable on both drivers via the schema builder's own
        // change()/dropForeign() so this migration runs identically in both.
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
        });

        // material_type has no real default going forward (the importer and
        // the manual form both always set it explicitly) -- the temporary
        // 'raw_material' default above only exists to backfill existing rows
        // without a NOT NULL violation; drop it now that backfill is done.
        Schema::table('products', function (Blueprint $table) {
            $table->string('material_type')->default(null)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->dropColumn(['material_type', 'created_by']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
```

- [ ] **Step 4: Update the `Product` model**

In `app/Models/Product.php`, add `material_type` and `created_by` to `$fillable`:

```php
    protected $fillable = [
        'seller_id', 'category_id', 'name', 'slug', 'sku', 'short_description',
        'description', 'features', 'applications', 'spec_sheet_path',
        'price_display', 'quantity', 'status', 'rejection_reason', 'sort_order',
        'material_type', 'created_by',
    ];
```

- [ ] **Step 5: Update the `Category` model**

In `app/Models/Category.php`, add the constant and extend `$fillable`:

```php
class Category extends Model
{
    use HasFactory, Searchable;

    public const PLACEHOLDER = 'TO BE ADDED';

    protected $fillable = [
        'parent_id', 'proposed_by_seller_id', 'name', 'slug', 'description', 'image', 'status', 'sort_order',
        'created_by',
    ];
```

- [ ] **Step 6: Update `ProductFactory`**

In `database/factories/ProductFactory.php`, add one line to the `definition()` array, after `'sort_order' => 0,`:

```php
            'material_type' => $this->faker->randomElement(['raw_material', 'finished_good']),
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=CategoryProductBulkImportSchemaTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — no other test asserts on `products.seller_id` being not-nullable at the DB level, or on the exact `$fillable` arrays.

- [ ] **Step 9: Apply the migration to the dev database and commit**

```bash
php artisan migrate
git add database/migrations/2026_08_23_100100_add_bulk_import_fields_to_products_and_categories.php app/Models/Product.php app/Models/Category.php database/factories/ProductFactory.php tests/Feature/CategoryProductBulkImportSchemaTest.php
git commit -m "feat: add material_type, created_by, and nullable seller_id for bulk import"
```

---

### Task 2: `material_type` on the manual admin and seller product forms

Requirement #5 explicitly wants this field settable "by excel import or by
manual page creation by an admin or seller" — the importer alone (Task 4)
does not satisfy that; both manual forms need it too.

**Files:**
- Modify: `app/Filament/Resources/ProductResource.php` (admin `form()`)
- Modify: `app/Filament/Seller/Resources/ProductResource.php` (seller `form()`)
- Test: `tests/Feature/ProductMaterialTypeFormTest.php`

**Interfaces:**
- Consumes: `products.material_type` (Task 1).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct as SellerCreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductMaterialTypeFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_set_material_type_when_creating_a_product(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
                'material_type' => 'finished_good',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'test-product')->firstOrFail();
        $this->assertSame('finished_good', $product->material_type);
    }

    public function test_material_type_is_required_on_the_admin_form(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
                'material_type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['material_type']);
    }

    public function test_a_seller_can_set_material_type_when_creating_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $category = Category::factory()->create();

        Livewire::test(SellerCreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Seller Product',
                'slug' => 'seller-product',
                'material_type' => 'raw_material',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'seller-product')->firstOrFail();
        $this->assertSame('raw_material', $product->material_type);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductMaterialTypeFormTest`
Expected: FAIL — neither form has a `material_type` field, so `fillForm` silently drops it and the create call either errors (required-column-with-no-default at the DB level, since Task 1 dropped the temporary default) or the assertions on the persisted product fail.

- [ ] **Step 3: Check both files' current imports first**

Read `app/Filament/Resources/ProductResource.php` and `app/Filament/Seller/Resources/ProductResource.php` before editing — confirm whether `Filament\Forms\Components\Select` is already imported in each (the admin one already imports it for `seller_id`/`category_id`; check the seller one too) so the new field doesn't need a duplicate `use`.

- [ ] **Step 4: Add the field to the admin form**

In `app/Filament/Resources/ProductResource.php`, add one field to `form()`, immediately after `Select::make('category_id')->label('Category')->options(fn () => CategoryHierarchy::options())->searchable()->required(),`:

```php
            Select::make('material_type')
                ->label('Raw Material or Finished Good')
                ->options(['raw_material' => 'Raw Material', 'finished_good' => 'Finished Good'])
                ->required(),
```

- [ ] **Step 5: Add the same field to the seller form**

In `app/Filament/Seller/Resources/ProductResource.php`, add the identical field to its `form()`, immediately after its own `Select::make('category_id')` block:

```php
            Select::make('material_type')
                ->label('Raw Material or Finished Good')
                ->options(['raw_material' => 'Raw Material', 'finished_good' => 'Finished Good'])
                ->required(),
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProductMaterialTypeFormTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Find every other test that creates a product via either form**

Run: `grep -rn "Livewire::test(CreateProduct::class)" tests/Feature`

Expected output (7 files, ~15 call sites, as of this plan being written — re-run the grep rather than trusting this list if the codebase has since changed):

```
tests/Feature/CategoryHierarchyTest.php
tests/Feature/ProductPriceFormattingTest.php
tests/Feature/ProductQuantityFieldTest.php
tests/Feature/ProductResourceDehydrationSecurityTest.php (4 occurrences)
tests/Feature/ProductRichTextFieldsTest.php
tests/Feature/SellerCategoryProposalTest.php (4 occurrences)
tests/Feature/SellerProductResourceTest.php (3 occurrences)
```

- [ ] **Step 8: Add `material_type` to every one of those `fillForm` calls**

For each `Livewire::test(CreateProduct::class)->fillForm([...])` found in Step 7,
add one line to the array: `'material_type' => 'raw_material',` (any valid
value works — these tests aren't testing `material_type` itself, they just
need the now-required field present so form validation doesn't reject the
whole submission). Do this for every occurrence in every file listed above,
including the seller-panel `CreateProduct` (the same class name resolves to
`App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct` in the
`SellerCategoryProposalTest`/`SellerProductResourceTest` files — check each
file's own `use` import to confirm which `CreateProduct` it is, and add the
field regardless of which one).

- [ ] **Step 9: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — every `CreateProduct` call site now supplies `material_type`,
and no other test creates a product through a route that bypasses these two
Filament forms (direct `Product::factory()->create()` calls already get a
value from Task 1's factory default).

- [ ] **Step 10: Commit**

```bash
git add app/Filament/Resources/ProductResource.php app/Filament/Seller/Resources/ProductResource.php tests/Feature/ProductMaterialTypeFormTest.php tests/Feature/CategoryHierarchyTest.php tests/Feature/ProductPriceFormattingTest.php tests/Feature/ProductQuantityFieldTest.php tests/Feature/ProductResourceDehydrationSecurityTest.php tests/Feature/ProductRichTextFieldsTest.php tests/Feature/SellerCategoryProposalTest.php tests/Feature/SellerProductResourceTest.php
git commit -m "feat: add Raw Material / Finished Good field to manual product forms"
```

---

### Task 3: `Product::publishBlockers()` seller check + `EditProduct` null-seller guard

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
- Test: `tests/Feature/ProductModelTest.php`
- Test: `tests/Feature/AdminProductEditTrailTest.php`

**Interfaces:**
- Consumes: `products.seller_id` nullable (Task 1).
- Produces: nothing consumed by later tasks — this task only makes the already-nullable column behave correctly everywhere it's read.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ProductModelTest.php` (inside the existing class):

```php
    public function test_a_product_with_no_seller_cannot_be_published(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create([
            'seller_id' => null,
            'category_id' => $category->id,
            'price_display' => '₹1,000',
            'status' => 'pending_review',
        ]);

        $result = $product->publish();

        $this->assertFalse($result);
        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_publish_blockers_reports_a_missing_seller(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create([
            'seller_id' => null,
            'category_id' => $category->id,
            'price_display' => '₹1,000',
        ]);

        $this->assertContains(
            "Assign a seller on the product's edit form before publishing.",
            $product->publishBlockers()
        );
    }
```

Add to `tests/Feature/AdminProductEditTrailTest.php` (inside the existing class — check its top-of-file imports already include `Product`, `Staff`, `Livewire`, and the `EditProduct`/`ListProducts` page classes; reuse them):

```php
    public function test_editing_a_seller_less_pending_review_product_sends_no_email_and_creates_no_trail(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'seller_id' => null,
            'status' => 'pending_review',
            'name' => 'Old Name',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'New Name'])
            ->call('save');

        $product->refresh();
        $this->assertSame('New Name', $product->name);
        $this->assertSame('pending_review', $product->status);
        $this->assertSame(0, $product->editTrails()->count());
        Mail::assertNothingQueued();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProductModelTest`
Expected: FAIL on the two new tests — `publishBlockers()` doesn't check `seller_id` yet, so `publish()` succeeds when it shouldn't.

Run: `php artisan test --filter=AdminProductEditTrailTest`
Expected: FAIL on the new test — editing a seller-less `pending_review` product currently still tries to email `null->email` and throws (or, if the test environment tolerates it differently, still creates an edit trail it shouldn't).

- [ ] **Step 3: Update `Product::publishBlockers()`**

In `app/Models/Product.php`, add a check to the `publishBlockers()` method:

```php
    public function publishBlockers(): array
    {
        $blockers = [];

        if (blank($this->seller_id)) {
            $blockers[] = "Assign a seller on the product's edit form before publishing.";
        }

        if (blank($this->price_display)) {
            $blockers[] = 'Set a price on the product’s edit form (the “Price” field, Admin only).';
        }

        if (! $this->category->isPublished()) {
            $blockers[] = 'Publish its category “'.$this->category->name.'” first — it is not live yet.';
        }

        return $blockers;
    }
```

- [ ] **Step 4: Update `EditProduct`'s early return**

In `app/Filament/Resources/ProductResource/Pages/EditProduct.php`, change:

```php
        if ($this->record->status !== 'pending_review') {
            return $data;
        }
```

to:

```php
        if ($this->record->status !== 'pending_review' || $this->record->seller_id === null) {
            return $data;
        }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ProductModelTest`
Expected: PASS (all tests in the file)

Run: `php artisan test --filter=AdminProductEditTrailTest`
Expected: PASS (all tests in the file)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — every existing product in every other test has a real `seller_id` (the factory default), so the new blocker/guard are no-ops for them.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Product.php app/Filament/Resources/ProductResource/Pages/EditProduct.php tests/Feature/ProductModelTest.php tests/Feature/AdminProductEditTrailTest.php
git commit -m "fix: block publishing and seller-notification emails for seller-less products"
```

---

### Task 4: `CategoryChainResolver` service

**Files:**
- Create: `app/Services/CategoryChainResolver.php`
- Test: `tests/Unit/CategoryChainResolverTest.php`

**Interfaces:**
- Consumes: `Category::PLACEHOLDER` (Task 1).
- Produces: `App\Services\CategoryChainResolver::resolve(array $row): Category`, where `$row` is
  `['parent_name' => ?string, 'parent_description' => ?string, 'sub1_name' => ?string, 'sub1_description' => ?string, 'sub2_name' => ?string, 'sub2_description' => ?string]`
  and the return value is the deepest `Category` actually present in the row (Parent if no sub-categories given, Sub-1 if no Sub-2, etc.). Used by Task 4 (`CategoryProductImporter`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\CategoryChainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryChainResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_parent_only_row_creates_one_draft_category(): void
    {
        $result = (new CategoryChainResolver())->resolve(['parent_name' => 'Plastic']);

        $this->assertSame('Plastic', $result->name);
        $this->assertNull($result->parent_id);
        $this->assertSame('draft', $result->status);
        $this->assertSame('admin_bulk_upload', $result->created_by);
    }

    public function test_a_three_level_row_creates_the_full_chain(): void
    {
        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Metal',
            'sub1_name' => 'Hardware-Screw',
            'sub2_name' => 'Washer/Nut/Bolts',
        ]);

        $this->assertSame('Washer/Nut/Bolts', $result->name);
        $this->assertSame('Hardware-Screw', $result->parent->name);
        $this->assertSame('Metal', $result->parent->parent->name);
        $this->assertNull($result->parent->parent->parent_id);
    }

    public function test_an_existing_category_is_reused_unchanged(): void
    {
        $existing = Category::factory()->create([
            'name' => 'Plastic',
            'parent_id' => null,
            'status' => 'published',
            'description' => 'Original description',
        ]);

        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'parent_description' => 'A different description from the sheet',
        ]);

        $this->assertTrue($result->is($existing));
        $this->assertSame('published', $result->fresh()->status);
        $this->assertSame('Original description', $result->fresh()->description);
    }

    public function test_two_different_parents_can_each_have_a_child_of_the_same_name(): void
    {
        $metalScrews = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Metal',
            'sub1_name' => 'Screws',
        ]);
        $plasticScrews = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'sub1_name' => 'Screws',
        ]);

        $this->assertNotSame($metalScrews->id, $plasticScrews->id);
        $this->assertSame('Screws', $metalScrews->name);
        $this->assertSame('Screws', $plasticScrews->name);
    }

    public function test_a_blank_name_at_an_implied_level_becomes_the_placeholder(): void
    {
        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => '',
            'sub1_name' => 'Cables and wires',
        ]);

        $this->assertSame('Cables and wires', $result->name);
        $this->assertSame(Category::PLACEHOLDER, $result->parent->name);
    }

    public function test_repeated_calls_reuse_the_same_chain_instead_of_duplicating_it(): void
    {
        (new CategoryChainResolver())->resolve(['parent_name' => 'Metal', 'sub1_name' => 'Screws']);
        (new CategoryChainResolver())->resolve(['parent_name' => 'Metal', 'sub1_name' => 'Screws']);

        $this->assertSame(1, Category::where('name', 'Metal')->count());
        $this->assertSame(1, Category::where('name', 'Screws')->count());
    }

    public function test_slugs_are_deduplicated_among_siblings(): void
    {
        Category::factory()->create(['parent_id' => null, 'name' => 'Metal', 'slug' => 'metal']);
        Category::factory()->create(['parent_id' => null, 'name' => 'Metal Works', 'slug' => 'metal']);

        $result = (new CategoryChainResolver())->resolve(['parent_name' => 'Metal!!']);

        $this->assertSame('metal-2', $result->slug);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CategoryChainResolverTest`
Expected: FAIL — `Class "App\Services\CategoryChainResolver" not found`.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryChainResolver
{
    /**
     * @param  array{parent_name?: ?string, parent_description?: ?string, sub1_name?: ?string, sub1_description?: ?string, sub2_name?: ?string, sub2_description?: ?string}  $row
     */
    public function resolve(array $row): Category
    {
        $levels = [
            [$row['parent_name'] ?? null, $row['parent_description'] ?? null],
            [$row['sub1_name'] ?? null, $row['sub1_description'] ?? null],
            [$row['sub2_name'] ?? null, $row['sub2_description'] ?? null],
        ];

        // A level only "counts" (and gets created, using the placeholder if
        // blank) when it or a deeper level actually has a name in the row --
        // trailing blank levels are simply absent, not placeholder rows.
        $deepestPresent = -1;
        foreach ($levels as $index => [$name]) {
            if (filled($name)) {
                $deepestPresent = $index;
            }
        }

        $parent = null;

        for ($index = 0; $index <= $deepestPresent; $index++) {
            [$name, $description] = $levels[$index];
            $name = filled($name) ? trim($name) : Category::PLACEHOLDER;

            $parent = $this->resolveLevel($name, $description, $parent?->id);
        }

        return $parent;
    }

    private function resolveLevel(string $name, ?string $description, ?int $parentId): Category
    {
        $existing = Category::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $parentId),
            'description' => filled($description) ? $description : null,
            'status' => 'draft',
            'created_by' => 'admin_bulk_upload',
        ]);
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('parent_id', $parentId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CategoryChainResolverTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/CategoryChainResolver.php tests/Unit/CategoryChainResolverTest.php
git commit -m "feat: add CategoryChainResolver service for bulk-import category trees"
```

---

### Task 5: `CategoryProductImporter` and the "Import Categories & Products" action

**Files:**
- Create: `app/Filament/Imports/CategoryProductImporter.php`
- Modify: `app/Filament/Resources/ProductResource/Pages/ListProducts.php`
- Test: `tests/Feature/CategoryProductImporterTest.php`

**Interfaces:**
- Consumes: `CategoryChainResolver::resolve()` (Task 4), `Product::$fillable` including `material_type`/`created_by` (Task 1).
- Produces: nothing consumed by later tasks except its own class name, referenced by Task 5's `AuditLog` friendly-label mapping and Task 6's `getCompletedNotificationBody()` update.

- [ ] **Step 1: Check `ListProducts`'s current contents**

Read `app/Filament/Resources/ProductResource/Pages/ListProducts.php` first — this repo's `ListProducts` may already have header actions or widgets (unlike `SellerResource`'s, which started empty). Add to whatever `getHeaderActions()` already returns; do not assume the file is empty.

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Models\Category;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImport(): Import
    {
        Import::polymorphicUserRelationship();

        return Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 1,
        ]);
    }

    private function columnMap(): array
    {
        return [
            'product_name' => 'product_name',
            'type' => 'type',
            'parent_name' => 'parent_name',
            'parent_description' => 'parent_description',
            'sub1_name' => 'sub1_name',
            'sub1_description' => 'sub1_description',
            'sub2_name' => 'sub2_name',
            'sub2_description' => 'sub2_description',
            'sku' => 'sku',
            'short_description' => 'short_description',
            'features' => 'features',
            'applications' => 'applications',
            'price_display' => 'price_display',
            'quantity' => 'quantity',
        ];
    }

    private function baseRow(array $overrides = []): array
    {
        return array_merge([
            'product_name' => 'PC DANA-BLACK',
            'type' => 'Raw Material',
            'parent_name' => 'Plastic',
            'parent_description' => 'This is dedicated plastics category',
            'sub1_name' => 'Plastic Granules',
            'sub1_description' => null,
            'sub2_name' => null,
            'sub2_description' => null,
            'sku' => 'RMPLGB0001',
            'short_description' => null,
            'features' => null,
            'applications' => null,
            'price_display' => null,
            'quantity' => null,
        ], $overrides);
    }

    public function test_a_row_with_no_product_name_creates_only_the_category_chain(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow(['product_name' => '']));

        $this->assertSame(2, Category::count());
        $this->assertSame(0, Product::count());
    }

    public function test_a_fully_populated_row_creates_the_category_chain_and_a_pending_review_product(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow());

        $product = Product::where('name', 'PC DANA-BLACK')->firstOrFail();
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->seller_id);
        $this->assertSame('admin_bulk_upload', $product->created_by);
        $this->assertSame('raw_material', $product->material_type);
        $this->assertSame('Plastic Granules', $product->category->name);
        $this->assertSame('Plastic', $product->category->parent->name);
    }

    public function test_finished_goods_maps_to_finished_material_type_case_insensitively(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow(['product_name' => 'CURROGATED BOX', 'type' => 'finished goods', 'sku' => 'FG0001']));

        $product = Product::where('name', 'CURROGATED BOX')->firstOrFail();
        $this->assertSame('finished_good', $product->material_type);
    }

    public function test_a_blank_type_cell_fails_the_row(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow(['type' => '']));

        $this->assertSame(0, Product::count());
    }

    public function test_a_row_matching_an_existing_product_in_the_same_category_is_skipped(): void
    {
        $category = (new \App\Services\CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'sub1_name' => 'Plastic Granules',
        ]);
        Product::factory()->create([
            'name' => 'PC DANA-BLACK',
            'category_id' => $category->id,
        ]);

        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);
        $importer($this->baseRow());

        $this->assertSame(1, Product::where('name', 'PC DANA-BLACK')->count());
    }

    public function test_the_import_action_is_available_on_the_admin_products_list(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $response = $this->get('/admin/products');

        $response->assertOk();
        $response->assertSee('Import Categories &amp; Products', false);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=CategoryProductImporterTest`
Expected: FAIL — `Class "App\Filament\Imports\CategoryProductImporter" not found`.

- [ ] **Step 4: Write `CategoryProductImporter`**

```php
<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Services\CategoryChainResolver;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('product_name')->label('Product NAME'),
            ImportColumn::make('type')->label('TYPE'),
            ImportColumn::make('parent_name')->label('PARENT CATEGORY NAME')->requiredMapping(),
            ImportColumn::make('parent_description')->label('PARENT CATEGORY Description'),
            ImportColumn::make('sub1_name')->label('Sub-Category-1 Name'),
            ImportColumn::make('sub1_description')->label('Sub-Category-1 Description'),
            ImportColumn::make('sub2_name')->label('Sub-Category-2 Name'),
            ImportColumn::make('sub2_description')->label('Sub-Category-2 Description'),
            ImportColumn::make('sku')->label('SKU / Product Number'),
            ImportColumn::make('short_description')->label('Product Short Description'),
            ImportColumn::make('features')->label('Product Feature'),
            ImportColumn::make('applications')->label('Product Application'),
            ImportColumn::make('price_display')->label('Price Range (INR)'),
            ImportColumn::make('quantity')->label('Quantity'),
        ];
    }

    public function resolveRecord(): ?Product
    {
        $category = (new CategoryChainResolver())->resolve([
            'parent_name' => $this->data['parent_name'] ?? null,
            'parent_description' => $this->data['parent_description'] ?? null,
            'sub1_name' => $this->data['sub1_name'] ?? null,
            'sub1_description' => $this->data['sub1_description'] ?? null,
            'sub2_name' => $this->data['sub2_name'] ?? null,
            'sub2_description' => $this->data['sub2_description'] ?? null,
        ]);

        $name = trim((string) ($this->data['product_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $existing = Product::query()
            ->where('category_id', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return null;
        }

        $product = new Product();
        $product->category_id = $category->id;
        $product->name = $name;
        $product->slug = $this->uniqueSlug($name, $category->id);

        return $product;
    }

    protected function beforeCreate(): void
    {
        $materialType = $this->normalizeMaterialType($this->data['type'] ?? null);

        if ($materialType === null) {
            throw ValidationException::withMessages([
                'type' => 'TYPE must be "Raw Material" or "Finished Good" (or "Finished Goods").',
            ]);
        }

        $this->record->material_type = $materialType;
        $this->record->status = 'pending_review';
        $this->record->seller_id = null;
        $this->record->created_by = 'admin_bulk_upload';
    }

    private function normalizeMaterialType(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match (true) {
            $normalized === 'raw material' => 'raw_material',
            in_array($normalized, ['finished good', 'finished goods'], true) => 'finished_good',
            default => null,
        };
    }

    private function uniqueSlug(string $name, int $categoryId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('category_id', $categoryId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your category & product import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
```

- [ ] **Step 5: Wire the "Import Categories & Products" action into `ListProducts`**

Add `Actions\ImportAction::make()->importer(CategoryProductImporter::class)->label('Import Categories & Products')` to whatever `getHeaderActions()` already returns in `app/Filament/Resources/ProductResource/Pages/ListProducts.php` (read the file per Step 1 before editing — do not overwrite existing actions/widgets), adding the `use App\Filament\Imports\CategoryProductImporter;` import alongside the existing ones.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=CategoryProductImporterTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Imports/CategoryProductImporter.php app/Filament/Resources/ProductResource/Pages/ListProducts.php tests/Feature/CategoryProductImporterTest.php
git commit -m "feat: bulk-import categories and products from Excel/CSV via Filament import"
```

---

### Task 6: `AuditLog` model, migration, and the dispatch-time capture hook

**Files:**
- Create: `database/migrations/2026_08_23_100200_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/AuditLogCaptureTest.php`

**Interfaces:**
- Consumes: `Filament\Actions\Imports\Models\Import` (already installed), `SellerImporter::class` and `CategoryProductImporter::class` (Task 5) as the two importer FQCNs to recognize.
- Produces: `App\Models\AuditLog` with columns `importer_label`, `performed_by_staff_id`, `file_name`, `total_rows`, `successful_rows`, `failed_rows`, `summary`, `filament_import_id` — Task 6 fills in the last four once an import completes; Task 7 reads all of them.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Filament\Imports\SellerImporter;
use App\Models\AuditLog;
use App\Models\Staff;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_category_product_import_records_an_audit_log_with_the_acting_staff(): void
    {
        Import::polymorphicUserRelationship();
        $admin = Staff::factory()->create();
        $this->actingAs($admin, 'staff');

        $import = Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 10,
        ]);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame('Category & Product Import', $log->importer_label);
        $this->assertSame($admin->id, $log->performed_by_staff_id);
        $this->assertSame('catalog.xlsx', $log->file_name);
    }

    public function test_creating_a_seller_import_records_an_audit_log_with_a_friendly_label(): void
    {
        Import::polymorphicUserRelationship();
        $admin = Staff::factory()->create();
        $this->actingAs($admin, 'staff');

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 5,
        ]);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame('Seller Import', $log->importer_label);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuditLogCaptureTest`
Expected: FAIL — `Class "App\Models\AuditLog" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('importer_label');
            $table->foreignId('performed_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('successful_rows')->nullable();
            $table->unsignedInteger('failed_rows')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('filament_import_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

- [ ] **Step 4: Write the `AuditLog` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'importer_label', 'performed_by_staff_id', 'file_name',
        'total_rows', 'successful_rows', 'failed_rows', 'summary',
        'filament_import_id',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by_staff_id');
    }
}
```

- [ ] **Step 5: Hook `Import::created` in `AdminPanelProvider`**

In `app/Providers/Filament/AdminPanelProvider.php`, add imports and update `boot()`:

```php
use App\Filament\Imports\CategoryProductImporter;
use App\Filament\Imports\SellerImporter;
use App\Models\AuditLog;
use Filament\Actions\Imports\Models\Import;
```

```php
    public function boot(): void
    {
        Import::polymorphicUserRelationship();

        Import::created(function (Import $import): void {
            $label = match ($import->importer) {
                SellerImporter::class => 'Seller Import',
                CategoryProductImporter::class => 'Category & Product Import',
                default => $import->importer,
            };

            AuditLog::create([
                'importer_label' => $label,
                'performed_by_staff_id' => auth('staff')->id(),
                'file_name' => $import->file_name,
                'filament_import_id' => $import->id,
            ]);
        });

        // ... existing render-hook registration for the resizable sidebar, unchanged
    }
```

- [ ] **Step 6: Add the `audit_logs` permission area**

In `database/seeders/RoleSeeder.php`, add `'audit_logs'` to `AREAS` and to the `admin` entry in `ROLE_MATRIX` (explicitly `null` for the other two roles, matching how `staff`/`settings` are already handled):

```php
    private const AREAS = ['dashboard', 'staff', 'roles', 'categories', 'products', 'sellers', 'quote_requests', 'pages', 'nav_items', 'settings', 'audit_logs'];
```

```php
        'admin' => [
            'dashboard' => 'full', 'staff' => 'full',
            'roles' => 'full',
            'categories' => 'full', 'products' => 'full', 'sellers' => 'full',
            'quote_requests' => 'full', 'pages' => 'full', 'nav_items' => 'full', 'settings' => 'full',
            'audit_logs' => 'full',
        ],
        'content_editor' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'full', 'products' => 'write', 'sellers' => null,
            'quote_requests' => null, 'pages' => 'full', 'nav_items' => 'full', 'settings' => null,
            'audit_logs' => null,
        ],
        'sales' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'read', 'products' => 'read', 'sellers' => null,
            'quote_requests' => 'write', 'pages' => 'read', 'nav_items' => 'read', 'settings' => null,
            'audit_logs' => null,
        ],
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AuditLogCaptureTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — no existing test enumerates `RoleSeeder::AREAS`/`ROLE_MATRIX` directly, so adding `audit_logs` to both is a no-op for every other test.

- [ ] **Step 9: Apply the migration to the dev database and commit**

```bash
php artisan migrate
git add database/migrations/2026_08_23_100200_create_audit_logs_table.php app/Models/AuditLog.php app/Providers/Filament/AdminPanelProvider.php database/seeders/RoleSeeder.php tests/Feature/AuditLogCaptureTest.php
git commit -m "feat: capture an AuditLog row for every bulk import at dispatch time"
```

---

### Task 7: `AuditLog::recordCompletion()` wired into both importers

**Files:**
- Modify: `app/Models/AuditLog.php`
- Modify: `app/Filament/Imports/SellerImporter.php`
- Modify: `app/Filament/Imports/CategoryProductImporter.php`
- Test: `tests/Feature/AuditLogCompletionTest.php`

**Interfaces:**
- Consumes: `AuditLog` (Task 6), both importers' existing `getCompletedNotificationBody()` methods.
- Produces: nothing consumed by later tasks — `AuditLog` rows are now fully populated after this task; Task 7 only reads them.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Models\AuditLog;
use App\Models\Staff;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_import_fills_in_the_counts_and_summary(): void
    {
        Import::polymorphicUserRelationship();
        $this->actingAs(Staff::factory()->create(), 'staff');

        $import = Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 10,
            'successful_rows' => 8,
        ]);

        CategoryProductImporter::getCompletedNotificationBody($import);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame(10, $log->total_rows);
        $this->assertSame(8, $log->successful_rows);
        $this->assertSame(2, $log->failed_rows);
        $this->assertStringContainsString('8', $log->summary);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuditLogCompletionTest`
Expected: FAIL — `total_rows`/`successful_rows`/`failed_rows`/`summary` are still `null` on the `AuditLog` row; `AuditLog::recordCompletion()` doesn't exist yet.

- [ ] **Step 3: Add `recordCompletion()` to `AuditLog`**

In `app/Models/AuditLog.php`, add:

```php
use Filament\Actions\Imports\Models\Import;
```

```php
    public static function recordCompletion(Import $import, string $summary): void
    {
        static::where('filament_import_id', $import->id)->update([
            'total_rows' => $import->total_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->getFailedRowsCount(),
            'summary' => $summary,
        ]);
    }
```

- [ ] **Step 4: Call it from `CategoryProductImporter::getCompletedNotificationBody()`**

In `app/Filament/Imports/CategoryProductImporter.php`, add the import and one line before `return $body;`:

```php
use App\Models\AuditLog;
```

```php
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your category & product import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        AuditLog::recordCompletion($import, $body);

        return $body;
    }
```

- [ ] **Step 5: Call it from `SellerImporter::getCompletedNotificationBody()`**

In `app/Filament/Imports/SellerImporter.php`, add the same import and the same one-line call before `return $body;` in its existing `getCompletedNotificationBody()`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=AuditLogCompletionTest`
Expected: PASS

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — the existing `SellerImporterTest` notification-body tests only assert on the returned string, which is unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Models/AuditLog.php app/Filament/Imports/SellerImporter.php app/Filament/Imports/CategoryProductImporter.php tests/Feature/AuditLogCompletionTest.php
git commit -m "feat: fill in AuditLog counts and summary when a bulk import completes"
```

---

### Task 8: `AuditLogResource` — the Audit Logs nav page

**Files:**
- Create: `app/Filament/Resources/AuditLogResource.php`
- Create: `app/Filament/Resources/AuditLogResource/Pages/ListAuditLogs.php`
- Create: `app/Policies/AuditLogPolicy.php`
- Test: `tests/Feature/AuditLogResourceTest.php`

**Interfaces:**
- Consumes: `AuditLog` (Tasks 6–7).
- Produces: nothing consumed by later tasks — this is the final task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_view_the_audit_logs_page_and_see_a_completed_import(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        AuditLog::create([
            'importer_label' => 'Category & Product Import',
            'performed_by_staff_id' => $admin->id,
            'file_name' => 'catalog.xlsx',
            'total_rows' => 10,
            'successful_rows' => 9,
            'failed_rows' => 1,
            'summary' => 'Your category & product import has completed and 9 rows imported.',
        ]);

        $response = $this->get('/admin/audit-logs');

        $response->assertOk();
        $response->assertSee('catalog.xlsx');
        $response->assertSee('Category & Product Import');
    }

    public function test_a_content_editor_cannot_view_the_audit_logs_page(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/audit-logs');

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuditLogResourceTest`
Expected: FAIL — `/admin/audit-logs` doesn't exist (404), no resource registered yet.

- [ ] **Step 3: Write `AuditLogPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Staff;

class AuditLogPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasPermissionTo('audit_logs.full');
    }

    public function view(Staff $staff, AuditLog $auditLog): bool
    {
        return $staff->hasPermissionTo('audit_logs.full');
    }
}
```

- [ ] **Step 4: Write `AuditLogResource`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('performedBy.name')->label('Imported By')->placeholder('—'),
                TextColumn::make('importer_label')->label('Type'),
                TextColumn::make('file_name')->label('File'),
                TextColumn::make('total_rows')->label('Total')->placeholder('—'),
                TextColumn::make('successful_rows')->label('Imported')->placeholder('—'),
                TextColumn::make('failed_rows')->label('Failed')->placeholder('—'),
                TextColumn::make('summary')->wrap(),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
```

- [ ] **Step 5: Write `ListAuditLogs`**

```php
<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=AuditLogResourceTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/AuditLogResource.php app/Filament/Resources/AuditLogResource/Pages/ListAuditLogs.php app/Policies/AuditLogPolicy.php tests/Feature/AuditLogResourceTest.php
git commit -m "feat: add read-only Audit Logs page for admin"
```

---

## After All Tasks: Manual Local Verification

The user wants to test this manually in local before merging. Once all 7 tasks are committed on this branch:

1. `php artisan migrate` (already run per-task, but confirm no pending migrations: `php artisan migrate:status`).
2. Start the app (`php artisan serve`), log in as admin.
3. On `/admin/products`, use "Import Categories & Products" with the attached sample sheet (mapping `Product NAME` → `product_name`, `TYPE` → `type`, `PARENT CATEGORY NAME` → `parent_name`, etc., `SKU / Product Number` → `sku`).
4. Confirm: the category tree appears under `/admin/categories`, all `draft`; the ~67 products appear under `/admin/products`, all `pending_review`, `material_type` set per row, no seller assigned.
5. Re-run the same file a second time; confirm no duplicate categories or products are created.
6. Check `/admin/audit-logs`: one row for this import, with the admin's name, file name, and correct counts.
7. Manually assign a seller to one imported product via its edit form, then try Publish — confirm it now succeeds (assuming its category is also published) and previously failed with the "Assign a seller" message before that.
