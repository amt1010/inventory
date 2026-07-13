# Seller Portal & Product Listing Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build out the actual content of the `/seller` Filament panel (scaffolded but
empty since the Seller Onboarding & Admin Approval phase): a seller-scoped
`ProductResource` for listing/editing their own products (no price field, ever), an
image gallery + custom-attributes relation manager, a per-product quote-request
count, and a profile + documents self-management page. Also completes the
already-spec'd-but-never-built Admin image gallery relation manager, sharing the
same component.

**Architecture:** New `App\Filament\Seller\Resources\ProductResource` alongside the
existing `App\Filament\Resources\ProductResource` (admin). Both share one
`ImagesRelationManager` component. Seller-side authorization deliberately does
**not** go through Laravel's `ProductPolicy` (see Global Constraints — reusing it
would crash) — the seller resource overrides Filament's `can*()` methods directly
with explicit ownership checks, and scopes `getEloquentQuery()` to the
authenticated seller. A `Profile` page (form) and `Documents` page (table) round
out seller self-service, mirroring the existing `DocumentsRelationManager` pattern
from the Admin `SellerResource`.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3 (`seller` guard/panel, already
scaffolded), MySQL (dev) / SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **No price field anywhere in the seller panel.** `price_display` is Admin-only
  per `App\Policies\ProductPolicy::setPrice()` — the seller's product form must not
  render it, not even disabled. (Spec: "Product Listing & Pricing Workflow" step 1
  — "no price field available to sellers".)
- **Sellers never get `quote_requests` row data** — not contact info, not
  messages, nothing. Only an aggregate count per product is allowed. (Spec:
  "Sellers never get access to this table.")
- **Seller-side authorization must not reuse `App\Policies\ProductPolicy`.** That
  policy's methods are type-hinted `Staff $staff` (see `app/Policies/ProductPolicy.php`).
  Laravel's `Gate::callPolicyMethod()` calls the policy method whenever the
  authenticated user is non-null, regardless of type — passing a `Seller` into a
  `Staff $staff` parameter throws an uncaught `TypeError`, not a clean 403. Filament
  Resources auto-discover `ProductPolicy` for the `Product` model no matter which
  panel/guard is asking, so the seller `ProductResource` **must** override
  `canViewAny()`, `canCreate()`, `canView()`, `canEdit()`, `canDelete()`, and
  `canDeleteAny()` directly with explicit `auth('seller')` / ownership checks,
  bypassing `static::can()` (which is what triggers the Gate call) entirely. Do not
  create a second `ProductPolicy` — Laravel only supports one policy class per
  model, and a second one would silently replace or conflict with the admin one.
- **Ownership scoping is enforced at the query level, not just the UI.** Every
  seller resource/page must scope its `getEloquentQuery()` (or table `query()`) to
  `where('seller_id', auth('seller')->id())` (or the equivalent owner-record
  relationship) so a seller hitting another seller's record URL directly gets a
  404, not just a hidden nav item.
- **A seller editing their own listing reverts a `published` product to
  `pending_review`.** This is scoped to the seller's own edit action only — Admin
  and Content Editor edits through `/admin` must NOT trigger this (spec: Content
  Editor "can still edit content fields... on already-published products for
  cleanup/quality control" without disrupting them). Do not implement this as a
  global Eloquent model event/observer — it belongs in the seller `EditProduct`
  page only.
- **`quantity` field required.** The spec's "Product Listing & Pricing Workflow"
  narrative lists "images, quantity" as seller-submitted fields, but the canonical
  "Data Model" section (the section every prior phase has treated as the
  authoritative schema) predates this workflow detail and has no `quantity`
  column on `products`. Task 1 adds a new migration for it — nullable unsigned
  integer, editable by both the seller (their own listings) and staff (Admin /
  Content Editor, as a content field, not a pricing field) — rather than skipping
  it.
- **Category picker is leaf-only.** Spec: `category_id` is "required, always a
  leaf." Filter the seller's category `Select` with
  `Category::query()->whereDoesntHave('children')`.
- `APP_TIMEZONE=Asia/Kolkata`; tests run against SQLite in-memory
  (`phpunit.xml`), never the dev MySQL database — don't touch that config.
- Every Resource/Page enforcing a boundary needs both the server-side check
  (policy override or explicit `where()`) and, for any field that must not be
  settable by the seller, the field must be **absent from the form schema
  entirely** (not merely `disabled()`), with the real value stamped server-side in
  `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave`.

---

## Context for the implementer

Existing pieces already in place (do not re-build these):
- `config/auth.php` has the `seller` guard/provider; `App\Models\Seller` is
  `Authenticatable` + `FilamentUser`, `canAccessPanel()` gates on `status ===
  'approved'`.
- `app/Providers/Filament/SellerPanelProvider.php` already discovers resources
  from `App\Filament\Seller\Resources` and pages from `App\Filament\Seller\Pages`
  (directories don't exist yet — you'll create them).
- `app/Models/Product.php`, `app/Models/SellerDocument.php`,
  `app/Models/CustomAttribute.php`, `app/Models/QuoteRequest.php` all already
  exist with the relations described in the Data Model spec section, except
  `Product::quoteRequests()` (Task 1 adds it).
- `database/factories/ProductFactory.php` and `SellerFactory.php` exist and are
  reusable in new tests.
- The Admin `App\Filament\Resources\ProductResource` (`app/Filament/Resources/ProductResource.php`)
  has no `getRelations()` yet — the image gallery relation manager the original
  spec called for was never built. Task 2 builds it and wires it into both panels.
- `App\Policies\ProductPolicy` (staff-guard only) and its RBAC tests must keep
  passing unchanged — this plan does not modify that file.

## Task 1: `quantity` column + Product model additions

**Files:**
- Create: `database/migrations/2026_07_13_150000_add_quantity_to_products_table.php`
- Modify: `app/Models/Product.php`
- Modify: `database/factories/ProductFactory.php`
- Test: `tests/Feature/ProductModelTest.php`

**Interfaces:**
- Produces: `products.quantity` (nullable unsigned integer column).
- Produces: `Product::quoteRequests(): HasMany` (used by Task 4's table column).
- Produces: `Product::statusAfterEdit(): string` — returns `'pending_review'` if
  the product's current `status` is `'published'`, otherwise returns the current
  `status` unchanged. Used by Task 5's seller `EditProduct` page.

- [ ] **Step 1: Write the failing tests**

Read the existing `tests/Feature/ProductModelTest.php` first so these fit its
existing style, then add:

```php
public function test_quantity_is_fillable_and_nullable(): void
{
    $product = Product::factory()->create(['quantity' => 500]);

    $this->assertSame(500, $product->fresh()->quantity);

    $productWithoutQuantity = Product::factory()->create(['quantity' => null]);

    $this->assertNull($productWithoutQuantity->fresh()->quantity);
}

public function test_quote_requests_relation_returns_related_quote_requests(): void
{
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    $match = QuoteRequest::factory()->create(['product_id' => $product->id]);
    QuoteRequest::factory()->create(['product_id' => $other->id]);

    $this->assertCount(1, $product->quoteRequests);
    $this->assertTrue($product->quoteRequests->first()->is($match));
}

public function test_status_after_edit_reverts_published_to_pending_review(): void
{
    $product = Product::factory()->create(['status' => 'published']);

    $this->assertSame('pending_review', $product->statusAfterEdit());
}

public function test_status_after_edit_leaves_non_published_status_unchanged(): void
{
    $product = Product::factory()->create(['status' => 'rejected']);

    $this->assertSame('rejected', $product->statusAfterEdit());
}
```

Add `use App\Models\QuoteRequest;` to the test file's imports if not already
present.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProductModelTest`
Expected: FAIL — `quantity` column, `quoteRequests` relation, and
`statusAfterEdit()` method don't exist yet.

- [ ] **Step 3: Add the migration**

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
            $table->unsignedInteger('quantity')->nullable()->after('price_display');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
```

Run: `php artisan migrate` (applies to the local dev MySQL database — the test
suite runs against SQLite in-memory per `phpunit.xml` and picks up new
migrations automatically on every `php artisan test` run).

- [ ] **Step 4: Implement the model and factory changes**

In `app/Models/Product.php`, add `'quantity'` to `$fillable` and add the import
and two new members:

```php
protected $fillable = [
    'seller_id', 'category_id', 'name', 'slug', 'sku', 'short_description',
    'description', 'features', 'applications', 'spec_sheet_path',
    'price_display', 'quantity', 'status', 'rejection_reason', 'sort_order',
];

// ... inside the class, alongside the other relations:

public function quoteRequests(): HasMany
{
    return $this->hasMany(QuoteRequest::class);
}

public function statusAfterEdit(): string
{
    return $this->status === 'published' ? 'pending_review' : $this->status;
}
```

(`HasMany` is already imported in this file for the `images()` relation — no new
`use` statement needed for that. `QuoteRequest` is in the same `App\Models`
namespace, also no import needed.)

In `database/factories/ProductFactory.php`, add a `quantity` value to
`definition()`:

```php
'quantity' => $this->faker->numberBetween(10, 1000),
```

(add it next to the existing `'price_display'` line — the fields around it are
unaffected).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ProductModelTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_13_150000_add_quantity_to_products_table.php \
        app/Models/Product.php \
        database/factories/ProductFactory.php \
        tests/Feature/ProductModelTest.php
git commit -m "Add products.quantity column, Product::quoteRequests() and statusAfterEdit()"
```

---

## Task 2: Shared image gallery relation manager + `quantity` field (wired into Admin's ProductResource)

**Files:**
- Create: `app/Filament/Resources/ProductResource/RelationManagers/ImagesRelationManager.php`
- Modify: `app/Filament/Resources/ProductResource.php` (add `getRelations()` and a
  `quantity` field/column)
- Test: `tests/Feature/ProductImagesRelationManagerTest.php`
- Test: `tests/Feature/ProductQuantityFieldTest.php`

**Interfaces:**
- Produces: `App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager`
  — reused verbatim (same class) by the seller `ProductResource` in Task 4. Bound
  to `Product::images()` (`app/Models/Product.php`, already exists), which maps to
  the existing `product_images` table (`path`, `sort_order`, `is_primary`).
- Consumes: `products.quantity` (Task 1's migration).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_upload_an_image_for_a_product(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create();

        Livewire::test(
            \App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager::class,
            ['ownerRecord' => $product, 'pageClass' => EditProduct::class]
        )
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('cable.jpg'),
                'is_primary' => true,
            ]);

        $this->assertSame(1, $product->images()->count());
        $this->assertTrue($product->images()->first()->is_primary);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductImagesRelationManagerTest`
Expected: FAIL — class `ImagesRelationManager` does not exist.

- [ ] **Step 3: Implement the relation manager**

```php
<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('path')
                ->label('Image')
                ->image()
                ->directory('product-images')
                ->required(),
            Toggle::make('is_primary')
                ->label('Primary image'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('Image'),
                IconColumn::make('is_primary')->boolean()->label('Primary'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
```

- [ ] **Step 4: Wire it into the Admin ProductResource**

In `app/Filament/Resources/ProductResource.php`, add the import and a
`getRelations()` method (Filament's `EditRecord` page already renders whatever
this returns — no page-class change needed, verified against
`vendor/filament/filament/src/Resources/Pages/EditRecord.php`, which includes
`Concerns\HasRelationManagers` by default):

```php
use App\Filament\Resources\ProductResource\RelationManagers;

// ... inside the class:

public static function getRelations(): array
{
    return [
        RelationManagers\ImagesRelationManager::class,
    ];
}
```

- [ ] **Step 5: Add the `quantity` field to the Admin form and table**

`quantity` (added in Task 1) is a content field, not a pricing field — Content
Editor is already authorized to `update()` products per
`App\Policies\ProductPolicy`, so unlike `price_display`/`status` this field does
not need the `disabled(! $canSetPrice)` gating.

First, add a failing test to a new file `tests/Feature/ProductQuantityFieldTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Seller;
use App\Models\Staff;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuantityFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_content_editor_can_set_quantity_via_the_admin_create_form(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Quantity Test Product',
                'slug' => 'quantity-test-product',
                'quantity' => 250,
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'quantity-test-product')->firstOrFail();

        $this->assertSame(250, $product->quantity);
    }
}
```

Run: `php artisan test --filter=ProductQuantityFieldTest`
Expected: FAIL — no `quantity` field on the form yet.

Then, in `app/Filament/Resources/ProductResource.php`, add a `TextInput` to
`form()` (place it right after the existing `sku` field) and a `TextColumn` to
`table()` (right after the existing `category.name` column):

```php
TextInput::make('quantity')
    ->numeric()
    ->minValue(0),
```

```php
TextColumn::make('quantity'),
```

Run: `php artisan test --filter=ProductQuantityFieldTest`
Expected: PASS

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProductImagesRelationManagerTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/ProductResource/RelationManagers/ImagesRelationManager.php \
        app/Filament/Resources/ProductResource.php \
        tests/Feature/ProductImagesRelationManagerTest.php \
        tests/Feature/ProductQuantityFieldTest.php
git commit -m "Add shared ImagesRelationManager and quantity field, wire into Admin ProductResource"
```

---

## Task 3: Seller ProductResource — scoped CRUD, no price, no status field

**Files:**
- Create: `app/Filament/Seller/Resources/ProductResource.php`
- Create: `app/Filament/Seller/Resources/ProductResource/Pages/ListProducts.php`
- Create: `app/Filament/Seller/Resources/ProductResource/Pages/CreateProduct.php`
- Create: `app/Filament/Seller/Resources/ProductResource/Pages/EditProduct.php`
- Test: `tests/Feature/SellerProductResourceTest.php`

**Interfaces:**
- Consumes: `Category::whereDoesntHave('children')` (leaf filter, no code change
  needed — `Category` model already exists with a `children()` relation).
- Consumes: `auth('seller')->id()` / `auth('seller')->user()` (seller guard,
  already configured in `config/auth.php`).
- Produces: route `/seller/products` (list), `/seller/products/create`,
  `/seller/products/{record}/edit` — used by Task 4 (relation managers) and
  Task 5 (status-revert-on-edit).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Seller\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_create_a_product_scoped_to_themselves(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $category = Category::factory()->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Aerial Fiber Cable',
                'slug' => 'aerial-fiber-cable',
                'quantity' => 1000,
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'aerial-fiber-cable')->firstOrFail();

        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->price_display);
        $this->assertSame(1000, $product->quantity);
    }

    public function test_a_tampered_payload_cannot_set_price_status_or_another_sellers_id_on_create(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();
        $category = Category::factory()->create();
        $this->actingAs($seller, 'seller');

        // price_display / status / seller_id have no form fields at all in the
        // seller resource -- attempting to inject them via fillForm() proves
        // they cannot reach the database regardless, since
        // mutateFormDataBeforeCreate() stamps seller_id/status unconditionally.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Sneaky Product',
                'slug' => 'sneaky-product',
                'price_display' => '₹9,999 hacked',
                'status' => 'published',
                'seller_id' => $otherSeller->id,
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'sneaky-product')->firstOrFail();

        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->price_display);
    }

    public function test_a_seller_only_sees_their_own_products_in_the_list(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownProduct = Product::factory()->create(['seller_id' => $seller->id]);
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$ownProduct])
            ->assertCanNotSeeTableRecords([$otherProduct]);
    }

    public function test_a_seller_cannot_open_another_sellers_product_edit_page(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller, 'seller');

        $response = $this->get("/seller/products/{$otherProduct->id}/edit");

        $response->assertNotFound();
    }

    public function test_category_options_exclude_categories_with_children(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create();
        $leaf = Category::factory()->create(['parent_id' => $parent->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($parent, $leaf) {
                $options = $field->getOptions();

                return array_key_exists($leaf->id, $options) && ! array_key_exists($parent->id, $options);
            });
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerProductResourceTest`
Expected: FAIL — resource/pages don't exist yet.

- [ ] **Step 3: Implement the resource**

```php
<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'My Products';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('seller_id', auth('seller')->id());
    }

    // Deliberately bypassing static::can() / Laravel's Gate for this resource --
    // see Global Constraints in the plan for why reusing App\Policies\ProductPolicy
    // (typed to Staff) would throw a TypeError for a Seller user.
    public static function canViewAny(): bool
    {
        return auth('seller')->check();
    }

    public static function canCreate(): bool
    {
        return auth('seller')->check();
    }

    public static function canView(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canEdit(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => Category::query()->whereDoesntHave('children')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')->required(),
            TextInput::make('sku')->label('SKU / Product Number'),
            TextInput::make('quantity')
                ->numeric()
                ->minValue(0),
            TextInput::make('short_description'),
            RichEditor::make('description'),
            Repeater::make('features')->simple(TextInput::make('value')->required()),
            Repeater::make('applications')->simple(TextInput::make('value')->required()),
            FileUpload::make('spec_sheet_path')
                ->label('Specification Sheet (PDF)')
                ->directory('spec-sheets')
                ->acceptedFileTypes(['application/pdf']),
            Placeholder::make('status_display')
                ->label('Status')
                ->content(fn (?Product $record) => $record
                    ? ucfirst(str_replace('_', ' ', $record->status))
                    : 'Pending Review (assigned on submit)'),
            Placeholder::make('rejection_reason')
                ->label('Rejection Reason')
                ->content(fn (?Product $record) => $record?->rejection_reason)
                ->visible(fn (?Product $record) => $record?->status === 'rejected'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('quantity'),
                TextColumn::make('status')->badge(),
                TextColumn::make('quote_requests_count')
                    ->counts('quoteRequests')
                    ->label('Quote Requests'),
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

Note: the Placeholder is named `status_display`, not `status`, to avoid colliding
with the real `status` database column/attribute during form state handling —
Placeholders aren't dehydrated (no data submitted), so this naming choice only
matters for avoiding confusion, but using a distinct name is clearer and avoids
any accidental future collision if a real `status` field were ever added to this
form.

```php
<?php

namespace App\Filament\Seller\Resources\ProductResource\Pages;

use App\Filament\Seller\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}
```

```php
<?php

namespace App\Filament\Seller\Resources\ProductResource\Pages;

use App\Filament\Seller\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['seller_id'] = auth('seller')->id();
        $data['status'] = 'pending_review';

        return $data;
    }
}
```

```php
<?php

namespace App\Filament\Seller\Resources\ProductResource\Pages;

use App\Filament\Seller\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
}
```

(`EditProduct`'s status-revert behavior is added in Task 5 — kept minimal here so
this task's tests stay focused on CRUD scoping.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerProductResourceTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS (all previously passing tests still pass)

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Seller/Resources/ProductResource.php \
        app/Filament/Seller/Resources/ProductResource/Pages/ListProducts.php \
        app/Filament/Seller/Resources/ProductResource/Pages/CreateProduct.php \
        app/Filament/Seller/Resources/ProductResource/Pages/EditProduct.php \
        tests/Feature/SellerProductResourceTest.php
git commit -m "Add seller-scoped ProductResource (no price/status fields)"
```

---

## Task 4: Images + custom attributes on the seller's own products

**Files:**
- Create: `app/Filament/Seller/Resources/ProductResource/RelationManagers/CustomAttributesRelationManager.php`
- Modify: `app/Filament/Seller/Resources/ProductResource.php` (add `getRelations()`)
- Test: `tests/Feature/SellerProductRelationManagersTest.php`

**Interfaces:**
- Consumes: `App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager`
  (Task 2, reused as-is — no seller-specific version needed since it only ever
  operates on the already-scoped owner record).
- Consumes: `Product::customAttributes(): MorphMany` (already exists in
  `app/Models/Product.php`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct as AdminEditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Seller\Resources\ProductResource\Pages\EditProduct as SellerEditProduct;
use App\Filament\Seller\Resources\ProductResource\RelationManagers\CustomAttributesRelationManager;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seller_can_upload_an_image_for_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => SellerEditProduct::class,
        ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('cable.jpg'),
                'is_primary' => true,
            ]);

        $this->assertSame(1, $product->images()->count());
    }

    public function test_seller_can_add_a_custom_attribute_to_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CustomAttributesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => SellerEditProduct::class,
        ])
            ->callTableAction('create', data: [
                'label' => 'Fiber Count',
                'value' => '96',
            ]);

        $this->assertSame(1, $product->customAttributes()->count());
        $this->assertSame('Fiber Count', $product->customAttributes()->first()->label);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerProductRelationManagersTest`
Expected: FAIL — `CustomAttributesRelationManager` doesn't exist, and the seller
`ProductResource` doesn't expose `getRelations()` yet.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Filament\Seller\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'customAttributes';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')->required(),
            TextInput::make('value'),
            FileUpload::make('file_path')->directory('product-custom-attributes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('value'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
```

In `app/Filament/Seller/Resources/ProductResource.php`, add the imports and method:

```php
use App\Filament\Resources\ProductResource\RelationManagers as AdminRelationManagers;
use App\Filament\Seller\Resources\ProductResource\RelationManagers;

// ... inside the class:

public static function getRelations(): array
{
    return [
        AdminRelationManagers\ImagesRelationManager::class,
        RelationManagers\CustomAttributesRelationManager::class,
    ];
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerProductRelationManagersTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Seller/Resources/ProductResource/RelationManagers/CustomAttributesRelationManager.php \
        app/Filament/Seller/Resources/ProductResource.php \
        tests/Feature/SellerProductRelationManagersTest.php
git commit -m "Wire images + custom attributes relation managers into seller ProductResource"
```

---

## Task 5: Seller edit reverts a published listing to pending_review

**Files:**
- Modify: `app/Filament/Seller/Resources/ProductResource/Pages/EditProduct.php`
- Test: `tests/Feature/SellerProductResourceTest.php` (append to the file from Task 3)

**Interfaces:**
- Consumes: `Product::statusAfterEdit()` (Task 1).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/SellerProductResourceTest.php`:

```php
    public function test_editing_a_published_product_as_the_owning_seller_reverts_it_to_pending_review(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'published',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);
        $this->actingAs($seller, 'seller');

        \Livewire\Livewire::test(\App\Filament\Seller\Resources\ProductResource\Pages\EditProduct::class, [
            'record' => $product->getRouteKey(),
        ])
            ->fillForm(['short_description' => 'Updated by seller'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame('Updated by seller', $product->short_description);
        // price_display must survive the edit -- reverting to pending_review is
        // not the same as clearing Admin's prior pricing decision.
        $this->assertSame('₹1,200 – ₹1,800 per reel', $product->price_display);
    }

    public function test_editing_a_pending_review_product_as_the_owning_seller_leaves_status_unchanged(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_review',
        ]);
        $this->actingAs($seller, 'seller');

        \Livewire\Livewire::test(\App\Filament\Seller\Resources\ProductResource\Pages\EditProduct::class, [
            'record' => $product->getRouteKey(),
        ])
            ->fillForm(['short_description' => 'Still pending'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending_review', $product->fresh()->status);
    }
```

Note: `tests/Feature/ProductResourceDehydrationSecurityTest.php`'s existing
`test_editing_an_already_published_product_does_not_error_when_status_is_left_unchanged`
already proves an Admin edit of a published product does **not** revert its
status — that test needs no changes and already guards the Admin-side half of
this Global Constraint.

- [ ] **Step 2: Run tests to verify the new one fails**

Run: `php artisan test --filter=SellerProductResourceTest`
Expected: FAIL on `test_editing_a_published_product_as_the_owning_seller_reverts_it_to_pending_review`
(status stays `published`).

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Filament\Seller\Resources\ProductResource\Pages;

use App\Filament\Seller\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['status'] = $this->record->statusAfterEdit();

        return $data;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerProductResourceTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Seller/Resources/ProductResource/Pages/EditProduct.php \
        tests/Feature/SellerProductResourceTest.php
git commit -m "Revert a published listing to pending_review when the owning seller edits it"
```

---

## Task 6: Seller profile + documents self-management

**Files:**
- Create: `app/Filament/Seller/Pages/Profile.php`
- Create: `resources/views/filament/seller/pages/profile.blade.php`
- Create: `app/Filament/Seller/Pages/Documents.php`
- Create: `resources/views/filament/seller/pages/documents.blade.php`
- Test: `tests/Feature/SellerProfileTest.php`
- Test: `tests/Feature/SellerDocumentsTest.php`

**Interfaces:**
- Consumes: `Seller` fillable fields (`app/Models/Seller.php`):
  `company_name`, `contact_person`, `phone`, `business_address`, `gst_number`.
  Deliberately excludes `email`, `status`, `created_by`, `rejection_reason`,
  `email_verified_at`, `approved_at`, `approved_by` — those are either
  login/identity fields or Admin-controlled workflow fields, not seller-editable
  profile data.
- Consumes: `SellerDocument` (`app/Models/SellerDocument.php`), same shape as the
  Admin `DocumentsRelationManager`
  (`app/Filament/Resources/SellerResource/RelationManagers/DocumentsRelationManager.php`) —
  reuse its `label`/`file_path`/`uploaded_at` field pattern.

- [ ] **Step 1: Write the failing profile test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Seller\Pages\Profile;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_view_and_update_their_profile(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'company_name' => 'Old Co',
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(Profile::class)
            ->assertFormSet(['company_name' => 'Old Co'])
            ->fillForm([
                'company_name' => 'New Co',
                'contact_person' => 'Jane Doe',
                'phone' => '9999999999',
                'business_address' => '123 Industrial Estate',
                'gst_number' => $seller->gst_number,
            ])
            ->call('save');

        $seller->refresh();
        $this->assertSame('New Co', $seller->company_name);
        $this->assertSame('Jane Doe', $seller->contact_person);
    }

    public function test_a_seller_cannot_change_their_own_status_via_the_profile_form(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        // 'status' has no form field on this page at all -- fillForm() setting
        // it directly proves it cannot reach the database regardless, since the
        // save() handler only ever writes the form's own defined fields.
        Livewire::test(Profile::class)
            ->fillForm([
                'company_name' => $seller->company_name,
                'contact_person' => $seller->contact_person,
                'phone' => $seller->phone,
                'business_address' => $seller->business_address,
                'gst_number' => $seller->gst_number,
                'status' => 'suspended',
            ])
            ->call('save');

        $this->assertSame('approved', $seller->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SellerProfileTest`
Expected: FAIL — `Profile` page doesn't exist.

- [ ] **Step 3: Implement the Profile page**

```php
<?php

namespace App\Filament\Seller\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.seller.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth('seller')->user()->only([
            'company_name', 'contact_person', 'phone', 'business_address', 'gst_number',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('company_name')->required(),
                TextInput::make('contact_person')->required(),
                TextInput::make('phone')->required(),
                TextInput::make('business_address'),
                TextInput::make('gst_number')->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        auth('seller')->user()->update($this->form->getState());

        Notification::make()->title('Profile updated')->success()->send();
    }
}
```

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="[
                \Filament\Actions\Action::make('save')->label('Save')->submit('save'),
            ]"
        />
    </form>
</x-filament-panels::page>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SellerProfileTest`
Expected: PASS

- [ ] **Step 5: Write the failing documents test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Seller\Pages\Documents;
use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SellerDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_seller_can_upload_a_document(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->callTableAction('create', data: [
                'label' => 'GST Certificate',
                'file_path' => UploadedFile::fake()->create('gst.pdf', 100),
            ]);

        $this->assertSame(1, $seller->documents()->count());
        $this->assertSame('GST Certificate', $seller->documents()->first()->label);
    }

    public function test_a_seller_can_delete_their_own_document(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $document = SellerDocument::factory()->for($seller)->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->callTableAction('delete', $document);

        $this->assertSame(0, $seller->documents()->count());
    }

    public function test_a_seller_only_sees_their_own_documents(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownDocument = SellerDocument::factory()->for($seller)->create();
        $otherDocument = SellerDocument::factory()->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->assertCanSeeTableRecords([$ownDocument])
            ->assertCanNotSeeTableRecords([$otherDocument]);
    }
}
```

If `database/factories/SellerDocumentFactory.php` doesn't exist yet, create it:

```php
<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerDocumentFactory extends Factory
{
    protected $model = SellerDocument::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'label' => $this->faker->words(2, true),
            'file_path' => 'seller-documents/'.$this->faker->uuid().'.pdf',
            'uploaded_at' => now(),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=SellerDocumentsTest`
Expected: FAIL — `Documents` page doesn't exist.

- [ ] **Step 7: Implement the Documents page**

```php
<?php

namespace App\Filament\Seller\Pages;

use App\Models\SellerDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Documents extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.seller.pages.documents';

    public function table(Table $table): Table
    {
        return $table
            ->query(SellerDocument::query()->where('seller_id', auth('seller')->id()))
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('uploaded_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('label')->required(),
                        FileUpload::make('file_path')->directory('seller-documents')->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['seller_id'] = auth('seller')->id();
                        $data['uploaded_at'] = now();

                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->defaultSort('uploaded_at', 'desc');
    }
}
```

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=SellerDocumentsTest`
Expected: PASS

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add app/Filament/Seller/Pages/Profile.php \
        resources/views/filament/seller/pages/profile.blade.php \
        app/Filament/Seller/Pages/Documents.php \
        resources/views/filament/seller/pages/documents.blade.php \
        tests/Feature/SellerProfileTest.php \
        tests/Feature/SellerDocumentsTest.php \
        database/factories/SellerDocumentFactory.php
git commit -m "Add seller Profile and Documents self-management pages"
```

---

## Task 7: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: All tests pass (existing suite + every test added in Tasks 1-6), 0 failures.

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: Only the files created/modified by Tasks 1-6 are listed. If any
`public/css/filament/*` or `public/js/filament/*` files show as modified with
only line-ending (CRLF/LF) noise, run `git diff -- public/` to confirm there is
no real content change, then `git checkout -- public/` to discard it — this is a
known, previously-verified benign artifact of the local environment (see
`CLAUDE.md`), not something to commit.

- [ ] **Step 3: Manually sanity-check panel navigation registers cleanly**

Run: `php artisan route:list --path=seller`
Expected: Routes exist for `/seller/products`, `/seller/products/create`,
`/seller/products/{record}/edit`, `/seller/profile` (or whatever slug Filament
assigns the `Profile` page by default), and `/seller/documents` (or the
`Documents` page's default slug), alongside the pre-existing `/seller/login` and
dashboard routes.
