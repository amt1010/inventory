# Content Pages & Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the block-based content-page system (Home, About, Policies, Contact-Us,
Resources — any `pages` row) and the CMS-managed header/footer navigation
(`nav_items`), completing the two remaining pieces of the public-facing site that
depend on neither Buyer Accounts nor further catalog work.

**Architecture:** A new `pages` table stores a JSON `content` column authored via
Filament's native `Builder` field — a reorderable list of typed blocks (Hero, Rich
Text, Featured Categories Grid, Featured Products Grid, RFQ Form Embed, Resource
List, FAQ/Accordion), each rendered by its own Blade partial. `PageController@show`
handles both `/` (the `home` page, not special-cased in the DB) and `/{slug}` for
every other page. A new `nav_items` table (self-referencing one level deep) drives
the header/footer navigation rendered globally via a view composer, so every page —
catalog, content, or otherwise — shares the same nav without each controller having
to fetch it.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3 (`Builder` form field, new
`/admin` resources), Blade + Bootstrap 5 (existing public-site convention), MySQL
(dev) / SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **Route ordering.** `/{slug}` is a catch-all single-path-segment route and must be
  registered **last** in `routes/web.php`, after `/search`, `/quote-requests`,
  every `/seller/*` route, and `/products/{path?}`. Laravel matches routes in
  registration order — registering `/{slug}` earlier would silently swallow
  `/search` and friends. `/products/{path?}` is unaffected regardless of order
  because it matches multi-segment paths (`->where('path', '.*')`), which
  `/{slug}`'s default single-segment parameter can never match.
- **The homepage is a normal `pages` row with slug `home` — not special-cased in
  the database.** Only the *route* for `/` is special: it points at the same
  `PageController@show` action with a route-level default of `slug = 'home'`
  (`Route::defaults('slug', 'home')`), not a separate controller method.
- **A page that is `draft` (or missing) 404s on the public site**, exactly like an
  unresolved category/product path — mirror `CatalogController`'s
  `abort(Response::HTTP_NOT_FOUND)` pattern (`app/Http/Controllers/CatalogController.php`)
  for consistency.
- **`nav_items` nesting is capped at one level.** An item that already has children
  cannot itself be assigned a parent (would silently produce two levels of
  nesting). Enforce this both in the form (only top-level items appear as
  selectable parents, and the field disables once a record has children) **and**
  as an explicit validation rule — per this codebase's established convention
  (see `CLAUDE.md`: "RBAC lives in Policies... `disabled()` alone is cosmetic and
  can be bypassed"), the same double-gating principle applies here even though
  this is a data-integrity rule rather than RBAC.
- **RFQ Form Embed reuses the existing quote-request form, not a copy.** The
  current `resources/views/partials/quote-request-form.blade.php` renders the
  form fields inside a Bootstrap modal (used on Product Detail pages). The
  content-page embed needs the same fields rendered **inline** (no modal — the
  spec's wording is "inline general-inquiry quote form"). Task 5 extracts the
  shared `<form>` body into `resources/views/partials/quote-request-form-fields.blade.php`
  so both the modal wrapper and the new inline block include the identical
  field markup — do not duplicate the field list.
- **`Category`/`Product` need a `path()` helper.** `CatalogController` currently
  builds a product/category's public URL from the request's own path segments
  (see `catalog/category.blade.php`'s breadcrumb construction) — that only works
  because the request *is* that path. The new Featured Categories/Products Grid
  blocks render on arbitrary content pages with no such context, so they need to
  compute a category/product's full nested URL independently. Task 4 adds
  `Category::path(): string` and `Product::path(): string` (walk `parent()` up to
  the root, join slugs with `/`) — reuse these, don't re-derive the path inline
  in the block partials.
- Every Resource enforcing a role boundary needs a Policy — mirror
  `App\Policies\CategoryPolicy` exactly for both `PagePolicy` and `NavItemPolicy`
  (Admin + Content Editor: full CRUD; Sales: view-only; same as Categories).
- `APP_TIMEZONE=Asia/Kolkata`; tests run against SQLite in-memory
  (`phpunit.xml`), never the dev MySQL database.

## Context for the implementer

Existing pieces you'll reuse or mirror (do not re-build these):
- `app/Http/Controllers/CatalogController.php` — the 404/published-only pattern
  to mirror in the new `PageController`.
- `app/Filament/Resources/CategoryResource.php` + `app/Policies/CategoryPolicy.php`
  — the exact structural precedent for `PageResource`/`PagePolicy` and
  `NavItemResource`/`NavItemPolicy` (self-referencing `parent_id`, slug-slugify
  live field, RBAC shape).
- `resources/views/partials/quote-request-form.blade.php` and
  `resources/views/catalog/product.blade.php` — read both before Task 5; the
  modal's `id="quoteRequestModal-{id}"` / `id="quoteRequestModal"` naming and the
  `config('rfq.*')` option lists (`reasons`, `countries`, `markets`,
  `contact_preferences`) must keep working unchanged after the extraction.
- `resources/views/layouts/app.blade.php` — the single shared layout; Task 6 adds
  a view composer so every page gets nav data without controllers passing it
  explicitly. `@section('content')` is the existing content slot.
- `app/Providers/AppServiceProvider.php` — currently an empty scaffold; Task 6's
  view composer goes in its `boot()` method.
- `database/seeders/DatabaseSeeder.php` — currently calls `RoleSeeder`,
  `StaffSeeder`, `CatalogSeeder` in that order; Task 6 appends new seeders so the
  app is navigable out of the box after `migrate:fresh --seed`.

## Task 1: `pages`/`nav_items` schema, models, policies

**Files:**
- Create: `database/migrations/2026_07_14_090000_create_pages_table.php`
- Create: `database/migrations/2026_07_14_090100_create_nav_items_table.php`
- Create: `app/Models/Page.php`
- Create: `app/Models/NavItem.php`
- Create: `app/Policies/PagePolicy.php`
- Create: `app/Policies/NavItemPolicy.php`
- Create: `database/factories/PageFactory.php`
- Create: `database/factories/NavItemFactory.php`
- Test: `tests/Feature/PagePolicyTest.php`
- Test: `tests/Feature/NavItemModelTest.php`

**Interfaces:**
- Produces: `Page::isPublished(): bool`, `content` cast to `array`.
- Produces: `NavItem::parent(): BelongsTo`, `NavItem::children(): HasMany`
  (ordered by `sort_order`) — consumed by Task 6's nav composer and Task 3's
  `NavItemResource` parent-picker.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/PagePolicyTest.php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_content_editor_can_create_and_update_pages(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($editor->can('create', Page::class));

        $page = Page::factory()->create();
        $this->assertTrue($editor->can('update', $page));
    }

    public function test_sales_can_view_but_not_create_or_update_pages(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $page = Page::factory()->create();

        $this->assertTrue($sales->can('viewAny', Page::class));
        $this->assertFalse($sales->can('create', Page::class));
        $this->assertFalse($sales->can('update', $page));
    }
}
```

```php
<?php
// tests/Feature/NavItemModelTest.php

namespace Tests\Feature;

use App\Models\NavItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nav_item_has_ordered_children(): void
    {
        $parent = NavItem::factory()->create(['location' => 'header']);
        $second = NavItem::factory()->create(['parent_id' => $parent->id, 'sort_order' => 2]);
        $first = NavItem::factory()->create(['parent_id' => $parent->id, 'sort_order' => 1]);

        $this->assertSame([$first->id, $second->id], $parent->children->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PagePolicyTest`
Run: `php artisan test --filter=NavItemModelTest`
Expected: FAIL — `Page`/`NavItem` models, policies, and factories don't exist yet.

- [ ] **Step 3: Add the migrations**

```php
<?php
// database/migrations/2026_07_14_090000_create_pages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('status')->default('draft'); // draft|published
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
```

```php
<?php
// database/migrations/2026_07_14_090100_create_nav_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('url');
            $table->string('location'); // header|footer
            $table->foreignId('parent_id')->nullable()->constrained('nav_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_items');
    }
};
```

- [ ] **Step 4: Add the models**

```php
<?php
// app/Models/Page.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'meta_title', 'meta_description', 'status',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
```

```php
<?php
// app/Models/NavItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'url', 'location', 'parent_id', 'sort_order',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavItem::class, 'parent_id')->orderBy('sort_order');
    }
}
```

- [ ] **Step 5: Add the policies**

```php
<?php
// app/Policies/PagePolicy.php

namespace App\Policies;

use App\Models\Page;
use App\Models\Staff;

class PagePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }
}
```

```php
<?php
// app/Policies/NavItemPolicy.php

namespace App\Policies;

use App\Models\NavItem;
use App\Models\Staff;

class NavItemPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }
}
```

- [ ] **Step 6: Add the factories**

```php
<?php
// database/factories/PageFactory.php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->words(3, true);

        return [
            'title' => ucwords($title),
            'slug' => Str::slug($title),
            'content' => [],
            'meta_title' => null,
            'meta_description' => null,
            'status' => 'draft',
        ];
    }
}
```

```php
<?php
// database/factories/NavItemFactory.php

namespace Database\Factories;

use App\Models\NavItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class NavItemFactory extends Factory
{
    protected $model = NavItem::class;

    public function definition(): array
    {
        return [
            'label' => ucwords($this->faker->words(2, true)),
            'url' => '/'.$this->faker->slug(2),
            'location' => 'header',
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=PagePolicyTest`
Run: `php artisan test --filter=NavItemModelTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_14_090000_create_pages_table.php \
        database/migrations/2026_07_14_090100_create_nav_items_table.php \
        app/Models/Page.php app/Models/NavItem.php \
        app/Policies/PagePolicy.php app/Policies/NavItemPolicy.php \
        database/factories/PageFactory.php database/factories/NavItemFactory.php \
        tests/Feature/PagePolicyTest.php tests/Feature/NavItemModelTest.php
git commit -m "Add pages/nav_items schema, models, and policies"
```

---

## Task 2: `PageResource` — block-based page builder

**Files:**
- Create: `app/Filament/Resources/PageResource.php`
- Create: `app/Filament/Resources/PageResource/Pages/ListPages.php`
- Create: `app/Filament/Resources/PageResource/Pages/CreatePage.php`
- Create: `app/Filament/Resources/PageResource/Pages/EditPage.php`
- Test: `tests/Feature/PageResourceTest.php`

**Interfaces:**
- Produces: route `/admin/pages` (list/create/edit) with a `content` `Builder`
  field storing an array of `{type, data, ...}` block entries — consumed by
  Task 5's rendering partials, which key off each entry's `type`.
- Block type keys used (must match exactly — Task 5's partials are named after
  these): `hero`, `rich_text`, `featured_categories`, `featured_products`,
  `rfq_form_embed`, `resource_list`, `faq`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Page;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_a_page_with_a_hero_block(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'About Us',
                'slug' => 'about',
                'status' => 'published',
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => ['heading' => 'Welcome', 'subheading' => 'We build cable.'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'about')->firstOrFail();

        $this->assertSame('hero', $page->content[0]['type']);
        $this->assertSame('Welcome', $page->content[0]['data']['heading']);
    }

    public function test_a_second_page_cannot_reuse_an_existing_slug(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Page::factory()->create(['slug' => 'contact-us']);

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Contact Us Duplicate',
                'slug' => 'contact-us',
                'status' => 'draft',
                'content' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_content_editor_gets_a_403_visiting_pages_if_not_authorized(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $response = $this->get('/admin/pages/create');

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PageResourceTest`
Expected: FAIL — `PageResource` doesn't exist.

- [ ] **Step 3: Implement the resource**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('meta_title'),
            Textarea::make('meta_description'),
            Select::make('status')
                ->options(['draft' => 'Draft', 'published' => 'Published'])
                ->default('draft')
                ->required(),
            Builder::make('content')
                ->blocks([
                    Block::make('hero')
                        ->schema([
                            TextInput::make('heading')->required(),
                            TextInput::make('subheading'),
                            FileUpload::make('background_image')
                                ->image()
                                ->directory('page-blocks'),
                            TextInput::make('cta_label'),
                            TextInput::make('cta_url'),
                        ]),
                    Block::make('rich_text')
                        ->label('Rich Text')
                        ->schema([
                            RichEditor::make('body')->required(),
                        ]),
                    Block::make('featured_categories')
                        ->label('Featured Categories Grid')
                        ->schema([
                            TextInput::make('heading'),
                            Select::make('category_ids')
                                ->label('Categories')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => Category::query()->where('status', 'published')->pluck('name', 'id'))
                                ->required(),
                        ]),
                    Block::make('featured_products')
                        ->label('Featured Products Grid')
                        ->schema([
                            TextInput::make('heading'),
                            Select::make('product_ids')
                                ->label('Products')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => Product::query()->where('status', 'published')->pluck('name', 'id'))
                                ->required(),
                        ]),
                    Block::make('rfq_form_embed')
                        ->label('RFQ Form Embed')
                        ->schema([
                            TextInput::make('heading')->default('Request a Quote'),
                        ]),
                    Block::make('resource_list')
                        ->label('Resource List')
                        ->schema([
                            TextInput::make('heading'),
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('title')->required(),
                                    Textarea::make('description'),
                                    TextInput::make('url')->label('Link URL')->url(),
                                    FileUpload::make('file')->directory('page-resources'),
                                ]),
                        ]),
                    Block::make('faq')
                        ->label('FAQ / Accordion')
                        ->schema([
                            TextInput::make('heading'),
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('question')->required(),
                                    Textarea::make('answer')->required(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('status')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
```

```php
<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

```php
<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
```

```php
<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PageResourceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/PageResource.php \
        app/Filament/Resources/PageResource/Pages/ListPages.php \
        app/Filament/Resources/PageResource/Pages/CreatePage.php \
        app/Filament/Resources/PageResource/Pages/EditPage.php \
        tests/Feature/PageResourceTest.php
git commit -m "Add PageResource with the 7-block Builder field"
```

---

## Task 3: `NavItemResource` — one-level header/footer navigation

**Files:**
- Create: `app/Filament/Resources/NavItemResource.php`
- Create: `app/Filament/Resources/NavItemResource/Pages/ListNavItems.php`
- Create: `app/Filament/Resources/NavItemResource/Pages/CreateNavItem.php`
- Create: `app/Filament/Resources/NavItemResource/Pages/EditNavItem.php`
- Test: `tests/Feature/NavItemResourceTest.php`

**Interfaces:**
- Produces: route `/admin/nav-items` — consumed by nobody else in this plan
  (Task 6 reads `NavItem` directly via Eloquent, not through this Resource).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\NavItemResource\Pages\CreateNavItem;
use App\Filament\Resources\NavItemResource\Pages\EditNavItem;
use App\Models\NavItem;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_a_top_level_header_nav_item(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateNavItem::class)
            ->fillForm([
                'label' => 'Products',
                'url' => '/products',
                'location' => 'header',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('nav_items', ['label' => 'Products', 'url' => '/products']);
    }

    public function test_an_item_with_children_cannot_be_nested_under_another_item(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $grandparent = NavItem::factory()->create(['location' => 'header']);
        $parentWithChildren = NavItem::factory()->create(['location' => 'header']);
        NavItem::factory()->create(['location' => 'header', 'parent_id' => $parentWithChildren->id]);

        Livewire::test(EditNavItem::class, ['record' => $parentWithChildren->getRouteKey()])
            ->fillForm(['parent_id' => $grandparent->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=NavItemResourceTest`
Expected: FAIL — `NavItemResource` doesn't exist.

- [ ] **Step 3: Implement the resource**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavItemResource\Pages;
use App\Models\NavItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NavItemResource extends Resource
{
    protected static ?string $model = NavItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')->required(),
            TextInput::make('url')
                ->required()
                ->helperText('e.g. /about or /products/fiber-optic-cable'),
            Select::make('location')
                ->options(['header' => 'Header', 'footer' => 'Footer'])
                ->required()
                ->live(),
            Select::make('parent_id')
                ->label('Parent Item')
                ->options(function (callable $get, ?NavItem $record) {
                    return NavItem::query()
                        ->whereNull('parent_id')
                        ->where('location', $get('location'))
                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                        ->pluck('label', 'id');
                })
                ->searchable()
                ->disabled(fn (?NavItem $record) => $record && $record->children()->exists())
                ->helperText(fn (?NavItem $record) => $record && $record->children()->exists()
                    ? 'This item has its own sub-items and cannot be nested under another item.'
                    : null)
                ->rule(function (?NavItem $record) {
                    return function (string $attribute, $value, \Closure $fail) use ($record) {
                        if ($value && $record && $record->children()->exists()) {
                            $fail('This item has sub-items and cannot be nested under another item.');
                        }
                    };
                }),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('url'),
                TextColumn::make('location')->badge(),
                TextColumn::make('parent.label')->label('Parent')->placeholder('— Top level —'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavItems::route('/'),
            'create' => Pages\CreateNavItem::route('/create'),
            'edit' => Pages\EditNavItem::route('/{record}/edit'),
        ];
    }
}
```

```php
<?php

namespace App\Filament\Resources\NavItemResource\Pages;

use App\Filament\Resources\NavItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNavItems extends ListRecords
{
    protected static string $resource = NavItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

```php
<?php

namespace App\Filament\Resources\NavItemResource\Pages;

use App\Filament\Resources\NavItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNavItem extends CreateRecord
{
    protected static string $resource = NavItemResource::class;
}
```

```php
<?php

namespace App\Filament\Resources\NavItemResource\Pages;

use App\Filament\Resources\NavItemResource;
use Filament\Resources\Pages\EditRecord;

class EditNavItem extends EditRecord
{
    protected static string $resource = NavItemResource::class;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=NavItemResourceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/NavItemResource.php \
        app/Filament/Resources/NavItemResource/Pages/ListNavItems.php \
        app/Filament/Resources/NavItemResource/Pages/CreateNavItem.php \
        app/Filament/Resources/NavItemResource/Pages/EditNavItem.php \
        tests/Feature/NavItemResourceTest.php
git commit -m "Add NavItemResource with one-level nesting enforced server-side"
```

---

## Task 4: `PageController`, routes, and `Category`/`Product` path helpers

**Files:**
- Create: `app/Http/Controllers/PageController.php`
- Modify: `routes/web.php`
- Modify: `app/Models/Category.php` (add `path()`)
- Modify: `app/Models/Product.php` (add `path()`)
- Test: `tests/Feature/PageRoutingTest.php`
- Test: `tests/Feature/CategoryPathTest.php` (append to existing `tests/Feature/ProductModelTest.php` is wrong file — create fresh)

**Interfaces:**
- Produces: `PageController::show(string $slug = 'home'): View` — resolves a
  published `Page` by slug or 404s.
- Produces: `Category::path(): string`, `Product::path(): string` — consumed by
  Task 5's `featured_categories`/`featured_products` block partials.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/PageRoutingTest.php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_published_page_with_slug_home(): void
    {
        Page::factory()->create(['slug' => 'home', 'title' => 'Welcome Home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Welcome Home');
    }

    public function test_missing_homepage_404s(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }

    public function test_a_published_page_resolves_by_slug(): void
    {
        Page::factory()->create(['slug' => 'about', 'title' => 'About Us', 'status' => 'published']);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('About Us');
    }

    public function test_a_draft_page_404s_on_the_public_site(): void
    {
        Page::factory()->create(['slug' => 'about', 'status' => 'draft']);

        $response = $this->get('/about');

        $response->assertNotFound();
    }

    public function test_search_still_resolves_ahead_of_the_catch_all_slug_route(): void
    {
        $response = $this->get('/search?q=cable');

        $response->assertOk();
    }
}
```

```php
<?php
// tests/Feature/CategoryPathTest.php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nested_categorys_path_joins_every_ancestor_slug(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'aerial']);
        $grandchild = Category::factory()->create(['parent_id' => $child->id, 'slug' => 'opgw']);

        $this->assertSame('fiber-optic-cable/aerial/opgw', $grandchild->path());
    }

    public function test_a_products_path_appends_its_slug_to_its_categorys_path(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);
        $product = Product::factory()->create(['category_id' => $root->id, 'slug' => 'centracore-opgw-cable']);

        $this->assertSame('fiber-optic-cable/centracore-opgw-cable', $product->path());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PageRoutingTest`
Run: `php artisan test --filter=CategoryPathTest`
Expected: FAIL — `PageController`, `Category::path()`, `Product::path()` don't exist; `/` and `/about` currently 404 or hit the old `welcome` view.

- [ ] **Step 3: Implement**

```php
<?php
// app/Http/Controllers/PageController.php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function show(string $slug = 'home'): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        abort_if(! $page, Response::HTTP_NOT_FOUND);

        return view('pages.show', ['page' => $page]);
    }
}
```

In `app/Models/Category.php`, add:

```php
public function path(): string
{
    $segments = [$this->slug];
    $parent = $this->parent;

    while ($parent) {
        array_unshift($segments, $parent->slug);
        $parent = $parent->parent;
    }

    return implode('/', $segments);
}
```

In `app/Models/Product.php`, add:

```php
public function path(): string
{
    return $this->category->path().'/'.$this->slug;
}
```

In `routes/web.php`, remove the existing `Route::get('/', function () { return view('welcome'); });` closure and add the `PageController` routes — the new `/{slug}` route **must be the last route in the file**:

```php
use App\Http\Controllers\PageController;

Route::get('/', [PageController::class, 'show'])->defaults('slug', 'home')->name('home');
```

(add this near the top, replacing the removed closure route — the *name* `home` is what matters for ordering purposes, not its position, since it has a fixed `/` URI that nothing else in this file also matches)

```php
// ... keep every existing route (search, quote-requests, seller/*, products/{path?}) unchanged, in place ...

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PageRoutingTest`
Run: `php artisan test --filter=CategoryPathTest`
Expected: PASS (Note: `pages.show` view doesn't exist until Task 5 — these tests
will fail with a view-not-found error until then unless you stub a minimal
`resources/views/pages/show.blade.php` now. Create a minimal stub for this task:
`@extends('layouts.app') @section('content') {{ $page->title }} @endsection` —
Task 5 replaces it with the real block-rendering version.)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — in particular confirm every existing `tests/Feature/CatalogRouting*`
/`CategoryTreeTest` style test still passes (the route-ordering change must not
break `/products/*`).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PageController.php routes/web.php \
        app/Models/Category.php app/Models/Product.php \
        resources/views/pages/show.blade.php \
        tests/Feature/PageRoutingTest.php tests/Feature/CategoryPathTest.php
git commit -m "Add PageController, homepage/slug routing, and Category/Product path() helpers"
```

---

## Task 5: Block rendering partials + RFQ form extraction

**Files:**
- Create: `resources/views/blocks/hero.blade.php`
- Create: `resources/views/blocks/rich_text.blade.php`
- Create: `resources/views/blocks/featured_categories.blade.php`
- Create: `resources/views/blocks/featured_products.blade.php`
- Create: `resources/views/blocks/rfq_form_embed.blade.php`
- Create: `resources/views/blocks/resource_list.blade.php`
- Create: `resources/views/blocks/faq.blade.php`
- Create: `resources/views/partials/quote-request-form-fields.blade.php`
- Modify: `resources/views/partials/quote-request-form.blade.php` (extract shared fields)
- Modify: `resources/views/pages/show.blade.php` (replace Task 4's stub)
- Test: `tests/Feature/PageBlockRenderingTest.php`

**Interfaces:**
- Consumes: `Page::content` (array of `{type, data}` entries from Task 2).
- Consumes: `Category::path()` / `Product::path()` (Task 4).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_block_renders_its_heading_and_cta(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'hero', 'data' => ['heading' => 'Welcome to AFL Marketplace', 'cta_label' => 'Browse Products', 'cta_url' => '/products']],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Welcome to AFL Marketplace');
        $response->assertSee('Browse Products');
    }

    public function test_a_featured_categories_block_links_to_the_full_nested_path(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable', 'status' => 'published']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'aerial', 'status' => 'published']);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['heading' => 'Popular Categories', 'category_ids' => [$child->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/products/fiber-optic-cable/aerial', escape: false);
    }

    public function test_a_featured_products_block_only_shows_published_products(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $published = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Published Widget']);
        $rejected = Product::factory()->create(['category_id' => $category->id, 'status' => 'rejected', 'name' => 'Rejected Widget']);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$published->id, $rejected->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Published Widget');
        $response->assertDontSee('Rejected Widget');
    }

    public function test_an_rfq_form_embed_block_renders_the_form_inline_without_a_modal(): void
    {
        Page::factory()->create([
            'slug' => 'contact-us',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => ['heading' => 'Get in Touch']],
            ],
        ]);

        $response = $this->get('/contact-us');

        $response->assertOk();
        $response->assertSee('Get in Touch');
        $response->assertSee(route('quote-requests.store'), escape: false);
        // Inline embed, not the Product Detail page's modal wrapper.
        $response->assertDontSee('modal fade', escape: false);
    }

    public function test_a_faq_block_renders_each_question_and_answer(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'faq', 'data' => ['items' => [['question' => 'Do you ship internationally?', 'answer' => 'Yes, globally.']]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Do you ship internationally?');
        $response->assertSee('Yes, globally.');
    }

    public function test_the_product_detail_pages_existing_modal_form_still_renders_after_the_extraction(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('id="quoteRequestModal-'.$product->id.'"', escape: false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: FAIL — block partials and the real `pages/show.blade.php` don't exist yet.

- [ ] **Step 3: Extract the shared RFQ form fields**

Read `resources/views/partials/quote-request-form.blade.php` first — copy its
entire `<form>...</form>` inner content (everything between `<form ...>` and
`</form>`, i.e. the `@csrf`, the errors block, the hidden `product_id`/`source_url`
inputs, and every visible field) into a new partial:

```blade
{{-- resources/views/partials/quote-request-form-fields.blade.php --}}
@php
    $defaultReason = isset($product) ? 'Request a Quote' : 'General Inquiry';
    $idSuffix = isset($product) ? '-'.$product->id : '';
@endphp

<form action="{{ route('quote-requests.store') }}" method="POST">
    @csrf
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @isset($product)
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <p class="text-muted">Regarding: <strong>{{ $product->name }}</strong></p>
    @endisset
    <input type="hidden" name="source_url" value="{{ url()->current() }}">

    <div class="mb-3">
        <label class="form-label">Reason for Contact</label>
        <select name="reason" class="form-select" required>
            @foreach (config('rfq.reasons') as $value => $label)
                <option value="{{ $value }}" @selected($value === $defaultReason)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Company</label>
            <input type="text" name="company" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Select Country</label>
            <select name="country" class="form-select">
                <option value="">Select Country</option>
                @foreach (config('rfq.countries') as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Select Market</label>
            <select name="market" class="form-select">
                <option value="">Select Market</option>
                @foreach (config('rfq.markets') as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" rows="4">{{ isset($product) ? 'I am interested in '.$product->name.' ('.url()->current().')' : '' }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label d-block">How would you prefer to be contacted?</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="contact_preference" value="email" id="contact-email{{ $idSuffix }}" checked>
            <label class="form-check-label" for="contact-email{{ $idSuffix }}">Email</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="contact_preference" value="phone" id="contact-phone{{ $idSuffix }}">
            <label class="form-check-label" for="contact-phone{{ $idSuffix }}">Phone</label>
        </div>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="privacy_policy" class="form-check-input" id="privacy{{ $idSuffix }}" required>
        <label class="form-check-label" for="privacy{{ $idSuffix }}">I have read and accepted the Privacy Policy.</label>
    </div>

    @if (config('services.recaptcha.site_key'))
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
    @endif

    <div class="d-flex justify-content-end gap-2 mt-2">
        @if ($modal ?? false)
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        @endif
        <button type="submit" class="btn btn-primary">Submit</button>
    </div>
</form>
```

The fields partial takes a `$modal` flag (`true` when wrapped in a Bootstrap
modal, `false` for an inline embed) purely to decide whether a "Cancel" button
makes sense — a modal has a dismiss target, an inline embed doesn't.

Then replace `quote-request-form.blade.php`'s body with the modal wrapper only,
including the fields partial with `modal => true`:

```blade
{{-- resources/views/partials/quote-request-form.blade.php --}}
@php
    $modalId = isset($product) ? 'quoteRequestModal-'.$product->id : 'quoteRequestModal';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request a Quote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('partials.quote-request-form-fields', ['product' => $product ?? null, 'modal' => true])
            </div>
        </div>
    </div>
</div>
```

(The original had the `<form>` wrap both `.modal-body` and `.modal-footer`, with
Cancel/Submit buttons in the footer. This version puts both buttons inside the
fields partial, inside `.modal-body`, and drops the separate `.modal-footer` —
visually equivalent, one fewer wrapper element.)

- [ ] **Step 4: Add the block partials**

```blade
{{-- resources/views/blocks/hero.blade.php --}}
<div class="p-5 mb-4 bg-light rounded-3 text-center"
     @if (!empty($data['background_image']))
         style="background-image: url('{{ asset('storage/'.$data['background_image']) }}'); background-size: cover; background-position: center;"
     @endif
>
    <h1>{{ $data['heading'] }}</h1>
    @if (!empty($data['subheading']))
        <p class="lead">{{ $data['subheading'] }}</p>
    @endif
    @if (!empty($data['cta_label']) && !empty($data['cta_url']))
        <a href="{{ $data['cta_url'] }}" class="btn btn-primary btn-lg">{{ $data['cta_label'] }}</a>
    @endif
</div>
```

```blade
{{-- resources/views/blocks/rich_text.blade.php --}}
<div class="mb-4">
    {!! $data['body'] ?? '' !!}
</div>
```

```blade
{{-- resources/views/blocks/featured_categories.blade.php --}}
@php
    $categories = \App\Models\Category::query()
        ->whereIn('id', $data['category_ids'] ?? [])
        ->where('status', 'published')
        ->get();
@endphp
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="row row-cols-1 row-cols-md-3 g-4">
        @foreach ($categories as $category)
            <div class="col">
                <a href="{{ url('/products/'.$category->path()) }}" class="card h-100 text-decoration-none">
                    @if ($category->image)
                        <img src="{{ asset('storage/'.$category->image) }}" class="card-img-top" alt="{{ $category->name }}">
                    @endif
                    <div class="card-body">
                        <h5 class="card-title text-dark">{{ $category->name }}</h5>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
```

```blade
{{-- resources/views/blocks/featured_products.blade.php --}}
@php
    $products = \App\Models\Product::with('images')
        ->whereIn('id', $data['product_ids'] ?? [])
        ->where('status', 'published')
        ->get();
@endphp
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="row row-cols-1 row-cols-md-3 g-4">
        @foreach ($products as $product)
            <div class="col">
                <a href="{{ url('/products/'.$product->path()) }}" class="card h-100 text-decoration-none">
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
</div>
```

```blade
{{-- resources/views/blocks/rfq_form_embed.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @include('partials.quote-request-form-fields', ['product' => null, 'modal' => false])
</div>
```

```blade
{{-- resources/views/blocks/resource_list.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <ul class="list-group">
        @foreach ($data['items'] ?? [] as $item)
            <li class="list-group-item">
                <strong>
                    @if (!empty($item['file']))
                        <a href="{{ asset('storage/'.$item['file']) }}">{{ $item['title'] }}</a>
                    @elseif (!empty($item['url']))
                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                    @else
                        {{ $item['title'] }}
                    @endif
                </strong>
                @if (!empty($item['description']))
                    <p class="mb-0 text-muted">{{ $item['description'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>
```

```blade
{{-- resources/views/blocks/faq.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="accordion" id="faqAccordion">
        @foreach ($data['items'] ?? [] as $item)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $loop->index }}">
                        {{ $item['question'] }}
                    </button>
                </h2>
                <div id="faq{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">{{ $item['answer'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 5: Replace the Task 4 stub with the real page template**

```blade
{{-- resources/views/pages/show.blade.php --}}
@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@if ($page->meta_description)
    @section('meta_description', $page->meta_description)
@endif

@section('content')
    @foreach ($page->content ?? [] as $block)
        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? []])
    @endforeach
@endsection
```

In `resources/views/layouts/app.blade.php`, add meta-description support to
`<head>` (right after the `<title>` line):

```blade
@hasSection('meta_description')
    <meta name="description" content="@yield('meta_description')">
@endif
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: PASS

- [ ] **Step 7: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — pay particular attention to any existing RFQ-form feature test
(from the RFQ phase) that submits through the Product Detail page's modal; the
extraction must not have changed field `name` attributes or the form's `action`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/blocks/ \
        resources/views/partials/quote-request-form-fields.blade.php \
        resources/views/partials/quote-request-form.blade.php \
        resources/views/pages/show.blade.php \
        resources/views/layouts/app.blade.php \
        tests/Feature/PageBlockRenderingTest.php
git commit -m "Add the 7 content-block partials and extract shared RFQ form fields"
```

---

## Task 6: Dynamic header/footer navigation + seed data

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/app.blade.php`
- Create: `database/seeders/PageSeeder.php`
- Create: `database/seeders/NavItemSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/NavigationRenderingTest.php`

**Interfaces:**
- Consumes: `NavItem::children()` (Task 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_nav_items_render_with_their_children_as_a_dropdown(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $parent = NavItem::factory()->create(['label' => 'Company', 'url' => '#', 'location' => 'header', 'sort_order' => 1]);
        NavItem::factory()->create(['label' => 'About Us', 'url' => '/about', 'location' => 'header', 'parent_id' => $parent->id, 'sort_order' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Company');
        $response->assertSee('About Us');
    }

    public function test_footer_nav_items_render(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        NavItem::factory()->create(['label' => 'Privacy Policy', 'url' => '/privacy', 'location' => 'footer', 'sort_order' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Privacy Policy');
    }

    public function test_seeded_home_and_contact_us_pages_are_reachable(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/contact-us')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NavigationRenderingTest`
Expected: FAIL — no nav data reaches the layout yet, and the seeders don't exist.

- [ ] **Step 3: Add the view composer**

In `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\NavItem;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $view->with('headerNavItems', NavItem::query()
                ->whereNull('parent_id')
                ->where('location', 'header')
                ->with('children')
                ->orderBy('sort_order')
                ->get());

            $view->with('footerNavItems', NavItem::query()
                ->whereNull('parent_id')
                ->where('location', 'footer')
                ->orderBy('sort_order')
                ->get());
        });
    }
}
```

- [ ] **Step 4: Render the nav in the layout**

In `resources/views/layouts/app.blade.php`, replace the existing `<nav>` block
with:

```blade
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                @foreach ($headerNavItems as $item)
                    @if ($item->children->isNotEmpty())
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                            <ul class="dropdown-menu">
                                @foreach ($item->children as $child)
                                    <li><a class="dropdown-item" href="{{ $child->url }}">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ $item->url }}">{{ $item->label }}</a></li>
                    @endif
                @endforeach
            </ul>
            <form class="d-flex" action="{{ route('catalog.search') }}" method="GET">
                <input class="form-control me-2" type="search" name="q" placeholder="Search for item by keyword or product number" value="{{ request('q') }}">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
        </div>
    </div>
</nav>
```

Add a footer just before the closing `</body>` tag:

```blade
<footer class="bg-light border-top py-4 mt-5">
    <div class="container">
        <ul class="list-inline mb-0">
            @foreach ($footerNavItems as $item)
                <li class="list-inline-item me-3"><a href="{{ $item->url }}">{{ $item->label }}</a></li>
            @endforeach
        </ul>
    </div>
</footer>
```

- [ ] **Step 5: Add the seeders**

```php
<?php
// database/seeders/PageSeeder.php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->firstOrCreate(['slug' => 'home'], [
            'title' => 'Home',
            'status' => 'published',
            'content' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Sourcing Cable & Wire, Simplified',
                        'subheading' => 'Browse our catalog and request a quote — no account required.',
                        'cta_label' => 'Browse Products',
                        'cta_url' => '/products',
                    ],
                ],
            ],
        ]);

        Page::query()->firstOrCreate(['slug' => 'contact-us'], [
            'title' => 'Contact Us',
            'status' => 'published',
            'content' => [
                [
                    'type' => 'rfq_form_embed',
                    'data' => ['heading' => 'Get in Touch'],
                ],
            ],
        ]);
    }
}
```

```php
<?php
// database/seeders/NavItemSeeder.php

namespace Database\Seeders;

use App\Models\NavItem;
use Illuminate\Database\Seeder;

class NavItemSeeder extends Seeder
{
    public function run(): void
    {
        NavItem::query()->firstOrCreate(
            ['label' => 'Products', 'location' => 'header'],
            ['url' => '/products', 'sort_order' => 1]
        );

        NavItem::query()->firstOrCreate(
            ['label' => 'Contact Us', 'location' => 'header'],
            ['url' => '/contact-us', 'sort_order' => 2]
        );

        NavItem::query()->firstOrCreate(
            ['label' => 'Contact Us', 'location' => 'footer'],
            ['url' => '/contact-us', 'sort_order' => 1]
        );
    }
}
```

In `database/seeders/DatabaseSeeder.php`, append the two new seeders:

```php
$this->call([
    RoleSeeder::class,
    StaffSeeder::class,
    CatalogSeeder::class,
    PageSeeder::class,
    NavItemSeeder::class,
]);
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=NavigationRenderingTest`
Expected: PASS

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php resources/views/layouts/app.blade.php \
        database/seeders/PageSeeder.php database/seeders/NavItemSeeder.php \
        database/seeders/DatabaseSeeder.php \
        tests/Feature/NavigationRenderingTest.php
git commit -m "Render header/footer navigation globally and seed a home/contact-us page"
```

---

## Task 7: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: All tests pass (existing suite + every test added in Tasks 1-6), 0 failures.

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: Only the files created/modified by Tasks 1-6 are listed. Discard any
benign CRLF-only `public/css/filament/*`/`public/js/filament/*` noise per the
pattern documented in `CLAUDE.md` (`git diff -- public/` to confirm zero content
change, then `git checkout -- public/`).

- [ ] **Step 3: Confirm route ordering did not regress catalog/search routes**

Run: `php artisan route:list`
Expected: `/search`, `/quote-requests`, every `/seller/*` route, `/admin/*`, and
`/products/{path?}` all still resolve to their existing controllers; `/{slug}`
(`pages.show`) is the last GET route in the list before Filament's own
catch-alls.

- [ ] **Step 4: Confirm the new Filament resources are reachable**

Run: `php artisan route:list --path=admin/pages`
Run: `php artisan route:list --path=admin/nav-items`
Expected: index/create/edit routes exist for both, matching the pattern of
`/admin/categories`.
