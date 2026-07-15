# Seller Category Proposals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a seller either pick an existing published category or propose a new
top-level or sub-category inline (via a Filament combo-box "create option" form)
when listing a product; the proposal lands as an ordinary unpublished category
that Admin reviews, corrects if needed, and publishes — after which the
associated product can proceed to `Product::publish()`.

**Architecture:** One additive migration/column (`categories.proposed_by_seller_id`)
and one new `Category` relation carry the entire feature — no new statuses, no
new Filament pages, no new emails. The seller's existing `category_id` Select
gains Filament's native `createOptionForm()`/`createOptionUsing()` plus a
visibility filter; the Admin's existing `CategoryResource` table gains one
column; `Product::publish()` gains one more precondition.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3 (`admin`/`staff` guard,
`seller`/`seller` guard), MySQL (dev) / SQLite in-memory (tests, per
`phpunit.xml`).

## Global Constraints

- Full design: `docs/superpowers/specs/2026-07-15-seller-category-proposals-design.md`
  — read before deviating.
- TDD throughout: write the failing test before the implementation, for every
  task.
- Commit after every task.
- No new `categories.status` value — a seller-proposed category is created with
  the *existing* `status = 'draft'`, identical in meaning to an admin's own
  not-yet-published category. `CatalogController` already scopes every public
  lookup to `status = 'published'`, so this requires no new guarding anywhere.
- `proposed_by_seller_id` is `null` for every category created through the
  existing Admin `CategoryResource` (including all pre-existing rows) — it only
  gets set by the seller's create-option flow this plan adds.
- A seller's category picker must only ever surface **leaf** categories as the
  product's own `category_id` (existing constraint, unchanged) — but the
  create-option form's own **Parent Category** picker may be *any* category
  (leaf or hub), since choosing a leaf as a parent is exactly how it becomes a
  hub.
- `products.status` and `categories.status` are both plain `string` columns (no
  DB enum) — nothing in this plan needs a schema change to either.

---

### Task 1: `categories.proposed_by_seller_id` column and `Category::proposedBy()`

**Files:**
- Create: `database/migrations/2026_07_15_130000_add_proposed_by_seller_id_to_categories_table.php`
- Modify: `app/Models/Category.php`
- Test: `tests/Feature/CategoryModelTest.php` (new file — no existing model-level
  test file for `Category` currently exists in this repo; `CategoryResourceTest.php`
  and `CategoryTreeTest.php`/`CategoryPathTest.php` cover Filament-resource and
  tree-traversal behavior respectively, not plain model relations)

**Interfaces:**
- Produces: `categories.proposed_by_seller_id` (nullable `foreignId`, FK →
  `sellers`, `nullOnDelete()`). `Category::proposedBy(): BelongsTo` — used by
  Task 2 (seller form) and Task 3 (Admin table column).

- [ ] **Step 1: Write the failing test**

`tests/Feature/CategoryModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_by_seller_id_is_nullable_and_fillable(): void
    {
        $category = Category::factory()->create(['proposed_by_seller_id' => null]);

        $this->assertNull($category->fresh()->proposed_by_seller_id);
    }

    public function test_a_category_can_belong_to_the_seller_who_proposed_it(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create(['proposed_by_seller_id' => $seller->id]);

        $this->assertTrue($category->proposedBy->is($seller));
    }

    public function test_proposed_by_is_null_when_no_seller_proposed_it(): void
    {
        $category = Category::factory()->create(['proposed_by_seller_id' => null]);

        $this->assertNull($category->proposedBy);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CategoryModelTest`
Expected: FAIL — `proposed_by_seller_id` isn't a fillable attribute and
`proposedBy()` doesn't exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_15_130000_add_proposed_by_seller_id_to_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('proposed_by_seller_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('sellers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_by_seller_id');
        });
    }
};
```

Run: `php artisan migrate` (applies to the local dev MySQL database — the test
suite runs against SQLite in-memory per `phpunit.xml` and picks up new
migrations automatically on every `php artisan test` run).

- [ ] **Step 4: Update the model**

In `app/Models/Category.php`, add `'proposed_by_seller_id'` to `$fillable`:

```php
protected $fillable = [
    'parent_id', 'proposed_by_seller_id', 'name', 'slug', 'description',
    'image', 'status', 'sort_order',
];
```

Add the relation (alongside `parent()`):

```php
public function proposedBy(): BelongsTo
{
    return $this->belongsTo(Seller::class, 'proposed_by_seller_id');
}
```

(`Seller` is in the same `App\Models` namespace — no new `use` statement
needed. `BelongsTo` is already imported for `parent()`.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CategoryModelTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_15_130000_add_proposed_by_seller_id_to_categories_table.php \
        app/Models/Category.php \
        tests/Feature/CategoryModelTest.php
git commit -m "Add categories.proposed_by_seller_id and Category::proposedBy()"
```

---

### Task 2: Seller category combo box — pick existing or propose new

**Files:**
- Modify: `app/Filament/Seller/Resources/ProductResource.php`
- Test: `tests/Feature/SellerCategoryProposalTest.php` (new file)

**Interfaces:**
- Consumes: `Category::proposedBy()` (Task 1, indirectly — this task only
  writes `proposed_by_seller_id` directly via `Category::create()`, it doesn't
  need to read the relation).
- Produces: no new public interface — this task only changes the seller
  `ProductResource` form's `category_id` field behavior.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SellerCategoryProposalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerCategoryProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_propose_a_new_top_level_category(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => null,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        $this->assertNull($category->parent_id);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_a_seller_can_propose_a_new_sub_category_under_an_existing_parent(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => $parent->id,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_proposing_a_category_immediately_selects_it_on_the_product_form(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => null,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable Two',
                'parent_id' => null,
            ])
            ->assertFormSet(['category_id' => Category::where('name', 'Submarine Cable Two')->firstOrFail()->id]);
    }

    public function test_proposing_a_category_with_a_name_that_collides_under_the_same_parent_is_rejected(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create(['status' => 'published']);
        Category::factory()->create(['parent_id' => $parent->id, 'name' => 'OPGW', 'slug' => 'opgw', 'status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'OPGW',
                'parent_id' => $parent->id,
            ])
            ->assertHasFormComponentActionErrors(['name']);

        $this->assertSame(1, Category::where('parent_id', $parent->id)->where('slug', 'opgw')->count());
    }

    public function test_a_sellers_own_pending_proposal_appears_in_their_own_dropdown(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownProposal = Category::factory()->create([
            'name' => 'Own Draft Category',
            'status' => 'draft',
            'proposed_by_seller_id' => $seller->id,
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($ownProposal) {
                return array_key_exists($ownProposal->id, $field->getOptions());
            });
    }

    public function test_another_sellers_pending_proposal_does_not_appear_in_the_dropdown(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();
        $othersProposal = Category::factory()->create([
            'name' => 'Other Sellers Draft',
            'status' => 'draft',
            'proposed_by_seller_id' => $otherSeller->id,
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($othersProposal) {
                return ! array_key_exists($othersProposal->id, $field->getOptions());
            });
    }

    public function test_the_draft_category_note_is_visible_only_when_the_selected_category_is_still_draft(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $draftCategory = Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $seller->id]);
        $publishedCategory = Category::factory()->create(['status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->fillForm(['category_id' => $draftCategory->id])
            ->assertFormFieldIsVisible('category_status_note')
            ->fillForm(['category_id' => $publishedCategory->id])
            ->assertFormFieldIsHidden('category_status_note');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerCategoryProposalTest`
Expected: FAIL — the `category_id` field has no create-option form yet, and
`category_status_note` doesn't exist.

- [ ] **Step 3: Update the seller `ProductResource` form**

In `app/Filament/Seller/Resources/ProductResource.php`, no new imports are
needed — `Category` and `Builder` are already imported (for the existing
category options query and `getEloquentQuery(): Builder`), `Str` is already
imported (for the product name→slug logic), and `auth('seller')` is a global
helper already used throughout this file.

Replace the `Select::make('category_id')` field in `form()`:

```php
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => static::categoryOptionsQuery()
                    ->whereDoesntHave('children')
                    ->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->createOptionForm([
                    Select::make('parent_id')
                        ->label('Parent Category')
                        ->options(fn () => static::categoryOptionsQuery()->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('— Top level (no parent) —'),
                    TextInput::make('name')
                        ->label('Category / Sub-Category Name')
                        ->required()
                        ->rule(fn (callable $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $slug = Str::slug($value);

                            if (Category::query()->where('parent_id', $get('parent_id'))->where('slug', $slug)->exists()) {
                                $fail('A category with a similar name already exists under the selected parent.');
                            }
                        }),
                ])
                ->createOptionUsing(function (array $data) {
                    return Category::create([
                        'parent_id' => $data['parent_id'] ?? null,
                        'name' => $data['name'],
                        'slug' => Str::slug($data['name']),
                        'status' => 'draft',
                        'proposed_by_seller_id' => auth('seller')->id(),
                    ])->id;
                }),
```

Add a new `Placeholder` immediately after the `category_id` field (still inside
`form()`'s `schema([...])` array):

```php
            Placeholder::make('category_status_note')
                ->label('')
                ->content('Category status: Draft — an administrator needs to review and publish this category before your product can go live.')
                ->visible(fn (callable $get) => optional(Category::find($get('category_id')))->status === 'draft'),
```

Add the new private static helper (alongside the other `public static function`
methods on the class, e.g. directly above `form()`):

```php
    private static function categoryOptionsQuery(): Builder
    {
        return Category::query()
            ->where(function (Builder $query) {
                $query->where('status', 'published')
                    ->orWhere(function (Builder $query) {
                        $query->where('status', 'draft')
                            ->where('proposed_by_seller_id', auth('seller')->id());
                    });
            });
    }
```

(`Category` and `Builder` are already imported in this file — `Category` for
the leaf-category options, `Builder` for `getEloquentQuery(): Builder`. `Str`
is already imported for the product name→slug logic. No new imports are
needed beyond what's already present.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerCategoryProposalTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — in particular, re-run
`php artisan test --filter=SellerProductResourceTest` to confirm the existing
`test_category_options_exclude_categories_with_children` test (which relies on
`category_id`'s options query) still passes with the new
`categoryOptionsQuery()` helper in place.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Seller/Resources/ProductResource.php \
        tests/Feature/SellerCategoryProposalTest.php
git commit -m "Let sellers propose a new category inline via a create-option combo box"
```

---

### Task 3: Admin Categories table — "Proposed By" column

**Files:**
- Modify: `app/Filament/Resources/CategoryResource.php`
- Test: `tests/Feature/CategoryResourceTest.php` (append to the existing file)

**Interfaces:**
- Consumes: `Category::proposedBy()` (Task 1).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/CategoryResourceTest.php` (inside the existing
`CategoryResourceTest` class — `Category`, `Staff`, `RoleSeeder`, `Livewire`,
and the admin `actingAs` setup pattern are already used by the existing test in
this file):

```php
    public function test_the_categories_table_shows_the_proposing_sellers_company_name(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = \App\Models\Seller::factory()->create(['company_name' => 'Rao Traders']);
        Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $seller->id]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSee('Rao Traders');
    }

    public function test_the_categories_table_shows_a_placeholder_for_admin_authored_categories(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Category::factory()->create(['name' => 'Admin Made This', 'proposed_by_seller_id' => null]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSeeText('—');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CategoryResourceTest`
Expected: FAIL — no "Proposed By" column exists yet, so neither the company
name nor the placeholder dash renders.

- [ ] **Step 3: Add the table column**

In `app/Filament/Resources/CategoryResource.php`, add the column to
`table()`'s `columns([...])`, immediately after the existing
`parent.name` column:

```php
                TextColumn::make('proposedBy.company_name')
                    ->label('Proposed By')
                    ->placeholder('—'),
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CategoryResourceTest`
Expected: PASS (3 tests — the 2 new ones plus the existing
`test_two_top_level_categories_cannot_share_a_slug`)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/CategoryResource.php \
        tests/Feature/CategoryResourceTest.php
git commit -m "Show the proposing seller's company name on the Admin Categories table"
```

---

### Task 4: `Product::publish()` requires a published category

**Files:**
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/ProductModelTest.php` (append to the existing file)

**Interfaces:**
- Consumes: `Category::isPublished(): bool` (already exists,
  `app/Models/Category.php`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ProductModelTest.php` (inside the existing
`ProductModelTest` class — `Category`, `Product`, `RefreshDatabase` are already
imported/used by the existing tests in this file):

```php
    public function test_a_product_cannot_be_published_while_its_category_is_still_draft(): void
    {
        $category = Category::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price_display' => '₹1,000 – ₹1,500',
            'status' => 'pending_review',
        ]);

        $result = $product->publish();

        $this->assertFalse($result);
        $this->assertSame('pending_review', $product->fresh()->status);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductModelTest`
Expected: FAIL — `publish()` currently only checks `price_display`, so this
product (which has a price but a draft category) incorrectly publishes.

- [ ] **Step 3: Update `Product::publish()`**

In `app/Models/Product.php`, replace the `publish()` method:

```php
    public function publish(): bool
    {
        if (blank($this->price_display)) {
            return false;
        }

        if (! $this->category->isPublished()) {
            return false;
        }

        $this->status = 'published';
        $this->save();

        return true;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductModelTest`
Expected: PASS — including the pre-existing
`test_a_product_with_a_price_can_be_published`, which is unaffected because
`ProductFactory`'s default `category_id` uses `Category::factory()`, whose
default `status` is already `'published'`.

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — this method is also exercised by the admin `publish` table
action (Task 13 of the previous catalog-fixes plan) and the seller
`acceptChanges` action; both already use categories with a default (published)
status in their existing tests, so no other test should be affected.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Product.php tests/Feature/ProductModelTest.php
git commit -m "Require a published category before Product::publish() succeeds"
```

---

### Task 5: Documentation update

**Files:**
- Modify: `CLAUDE.md`

**Interfaces:** none — documentation only, no test.

- [ ] **Step 1: Update the Architecture map**

In `CLAUDE.md`, find the `Category` bullet in the "Architecture map" section:

```markdown
- `app/Models/Category.php` — self-referencing tree (`parent_id`), any depth. A
  category with children renders as a hub; one without renders its products.
```

Replace it with:

```markdown
- `app/Models/Category.php` — self-referencing tree (`parent_id`), any depth. A
  category with children renders as a hub; one without renders its products.
  Sellers may propose a new leaf category inline from the product form (a
  Filament create-option combo box on `category_id`); the proposal lands as an
  ordinary `status = 'draft'` category tagged with `proposed_by_seller_id` —
  invisible to buyers until Admin reviews, optionally corrects (name, slug,
  parent), and publishes it via the existing `/admin/categories` screen, at
  which point the associated product's own review can proceed to
  `Product::publish()` (which now also requires the category to be published).
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "Document the seller category-proposal workflow"
```

---

### Task 6: Full verification pass

**Files:** none (verification only)

**Interfaces:** none — this task confirms Tasks 1-5 integrate correctly.

- [ ] **Step 1: Full reset and full test suite**

```bash
php artisan migrate:fresh --seed
php artisan test
```

Expected: no errors; all tests pass.

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: only files created/modified by Tasks 1-5 are listed. If any
`public/css/filament/*` or `public/js/filament/*` files show as modified with
only line-ending (CRLF/LF) noise, run `git diff -- public/` to confirm there is
no real content change, then `git checkout -- public/` to discard it — this is
a known, previously-verified benign artifact of the local environment (see
`CLAUDE.md`), not something to commit.

- [ ] **Step 3: Manual smoke test**

```bash
php artisan serve
```

- Log into `/seller` with an approved seller, go to My Products → New. On the
  Category field, click the "+" (create option) button. Leave Parent Category
  blank, type a new name, submit — confirm the field now shows your new
  category selected, and the "Category status: Draft" note appears.
- Create another new category, this time picking an existing category as
  Parent Category — confirm it's created as a sub-category.
- In `/admin`, open Categories, confirm both new categories appear with status
  "Draft" and "Proposed By" showing your seller's company name. Edit one,
  correct its name if you like, set status to Published, save.
- Back in `/seller`, create a second product using the now-published category
  — confirm the "Category status: Draft" note does **not** appear for it.
- In `/admin` → Products, confirm the first product's category still shows
  Draft, and confirm the `publish` table action either doesn't publish it (if
  you try) or is otherwise blocked until you publish its category — set the
  product's price via edit, then attempt Publish before publishing its
  category, and confirm it does not become published; publish the category,
  then Publish the product again and confirm it succeeds.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "Seller category proposals plan complete: verified end-to-end"
```
