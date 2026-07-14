# Premium Storefront Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the public storefront an Alibaba.com-inspired visual identity, an admin-editable site logo/name, a live-category mega-menu on the header, and two new homepage content blocks (a show/hide-able hero carousel supporting image or video slides, and an image+text content strip) — closing out the design/UX feedback gathered after the Buyer Accounts phase shipped.

**Architecture:** Everything here is additive to the existing Blade + Bootstrap 5 (CDN) public site and the existing block-based Page/Builder system from the Content Pages & Navigation phase — no new frontend framework, no Vite build step (the public site never used Vite; introducing it now would add an undocumented `npm run build` deploy step for no benefit over a plain linked stylesheet). Branding is a new singleton `settings` row edited via a dedicated Filament admin page (not a Resource — there's exactly one row, so a resource's list/create/delete UX doesn't fit). The mega-menu reads the live `Category` tree directly (not `NavItem`'s manually-maintained children) so it can never drift out of sync with the actual catalog, following the existing "Categories are one self-referencing table" convention.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3, Blade + Bootstrap 5 (CDN, unchanged), MySQL (dev) / SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **No Vite/build-step CSS.** The public site (`layouts/app.blade.php`) has never used `@vite()` — it links Bootstrap directly from a CDN. The new stylesheet is a plain static file at `public/css/site.css`, linked directly, matching that existing convention. Do not introduce `resources/css/app.css` + `@vite()` for this.
- **No new guard, no Filament panel changes beyond adding one Settings page.** Settings management lives on `/admin` (staff guard), restricted to the `admin` role only via a Policy (`SettingPolicy::manage`) — Content Editor and Sales must not see or reach it, matching the existing RBAC convention ("RBAC lives in Policies, not just Filament form visibility").
- **Mega-menu reads live `Category` data, never duplicates it into `NavItem`.** Do not backfill the category tree into `nav_items` rows — that would reintroduce exactly the kind of manually-synced, drift-prone structure this codebase's Category self-referencing table was built to avoid.
- **Additive, not replacing, existing block types.** The existing `hero` block type (single hero, no carousel) stays as-is — `hero_carousel` is a new, additional Builder block option. Do not remove or rename `hero`; existing seeded/authored pages and `PageBlockRenderingTest` depend on it.
- **Every new/changed Filament FileUpload continues to store under `storage/app/public/...`** and is served via `storage:link` (already documented in `CLAUDE.md` as of the prior bug-fix commit) — no new storage disk.
- `APP_TIMEZONE=Asia/Kolkata`; tests run against SQLite in-memory (`phpunit.xml`), never the dev MySQL database.

## Context for the implementer

Existing pieces already in place (do not re-build these):
- `app/Models/Category.php` — self-referencing tree with `path()`, `children()` (ordered by `sort_order`), `status` (`draft`/`published`), and an existing `image` column already used by `CategoryResource` and `resources/views/catalog/category.blade.php`.
- `app/Models/NavItem.php` + `app/Filament/Resources/NavItemResource.php` — self-referencing header/footer nav with a one-level nesting cap (from the Content Pages & Navigation phase). `fillable = ['label', 'url', 'location', 'parent_id', 'sort_order']`.
- `app/Providers/AppServiceProvider.php::boot()` — already has a `View::composer('layouts.app', ...)` closure providing `$headerNavItems` and `$footerNavItems`. Add to this same closure; do not create a second composer on the same view.
- `resources/views/layouts/app.blade.php` — the shared layout every page extends. Current header: brand link (`{{ config('app.name') }}` as plain text) → collapsible nav (`#mainNav`) → `$headerNavItems` loop (dropdown if `$item->children->isNotEmpty()`, else plain link) → search form → auth-chrome `<ul>` (login/register or favorites/history/logout, ending with a "Login as Seller" link, just added in the immediately-prior bug-fix commit).
- `app/Filament/Resources/PageResource.php` — the `Builder::make('content')->blocks([...])` array currently has 7 blocks: `hero`, `rich_text`, `featured_categories`, `featured_products`, `rfq_form_embed`, `resource_list`, `faq`. `resources/views/blocks/*.blade.php` has one partial per key, included by `resources/views/pages/show.blade.php` via `@includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? [], 'blockKey' => $loop->index])`. **Every new block type must accept and use `$blockKey`** if it renders any `id=` attribute, to avoid the DOM-id-collision bug already fixed once in this codebase for `faq`/`rfq_form_embed`.
- `resources/views/blocks/featured_products.blade.php` — already renders thumbnails + links correctly (`asset('storage/'.$product->images->first()->path)`, ordered to match the admin's chosen `product_ids` order). No changes needed here.
- `database/seeders/PageSeeder.php`, `database/seeders/NavItemSeeder.php`, `database/seeders/DatabaseSeeder.php` — existing seeders for the demo home/contact-us pages and header/footer nav items.
- `app/Filament/Resources/CategoryResource.php` — categories already have an admin-uploadable `image` field.
- `App\Policies\CategoryPolicy` — the reference pattern for the new `SettingPolicy` (role-gated by `hasAnyRole`/`hasRole`).
- `app/Providers/Filament/AdminPanelProvider.php` already has `->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')` — a new class dropped in `app/Filament/Pages/` is auto-registered, no manual wiring needed.

## Task 1: Site branding (Settings singleton, admin page, header logo/name)

**Files:**
- Create: `database/migrations/2026_07_14_180000_create_settings_table.php`
- Create: `app/Models/Setting.php`
- Create: `app/Policies/SettingPolicy.php`
- Create: `app/Filament/Pages/ManageSettings.php`
- Create: `resources/views/filament/pages/manage-settings.blade.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/SettingsTest.php`

**Interfaces:**
- Produces: `Setting::current(): Setting` (a static accessor that always returns the single settings row, creating it with defaults on first access). Consumed by this task's own view composer and later tasks needn't touch it.
- Produces: `$siteSettings` variable available on every page via the `layouts.app` view composer.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_current_creates_a_default_row_on_first_access(): void
    {
        $this->assertDatabaseCount('settings', 0);

        $setting = Setting::current();

        $this->assertDatabaseCount('settings', 1);
        $this->assertSame($setting->id, Setting::current()->id);
    }

    public function test_the_public_layout_shows_the_configured_site_name_when_there_is_no_logo(): void
    {
        Setting::current()->update(['site_name' => 'Acme Cable Co.']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Acme Cable Co.');
    }

    public function test_an_admin_can_access_the_settings_page(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin/manage-settings');

        $response->assertOk();
    }

    public function test_a_content_editor_cannot_access_the_settings_page(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('content_editor');

        $response = $this->actingAs($staff, 'staff')->get('/admin/manage-settings');

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SettingsTest`
Expected: FAIL — `settings` table, `Setting` model, and the admin page don't exist yet.

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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Platform');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 4: Add the model**

```php
<?php
// app/Models/Setting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['site_name', 'logo_path'];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['site_name' => config('app.name')]);
    }
}
```

- [ ] **Step 5: Add the policy**

```php
<?php
// app/Policies/SettingPolicy.php

namespace App\Policies;

use App\Models\Setting;
use App\Models\Staff;

class SettingPolicy
{
    public function manage(Staff $staff, ?Setting $setting = null): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 6: Add the Filament settings page**

```php
<?php
// app/Filament/Pages/ManageSettings.php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('staff')->user()?->can('manage', Setting::class) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Setting::current()->only(['site_name', 'logo_path']));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('site_name')->required(),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->directory('branding'),
        ])->statePath('data');
    }

    public function save(): void
    {
        Setting::current()->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}
```

```blade
{{-- resources/views/filament/pages/manage-settings.blade.php --}}
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Save
        </x-filament::button>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 7: Wire the view composer and header brand rendering**

In `app/Providers/AppServiceProvider.php`, add the `Setting` import and extend the existing composer closure (do not add a second `View::composer('layouts.app', ...)` call):

```php
use App\Models\Setting;
```

```php
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

            $view->with('siteSettings', Setting::current());
        });
```

In `resources/views/layouts/app.blade.php`, replace the brand link:

```blade
            <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
```

with:

```blade
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                @if ($siteSettings->logo_path)
                    <img src="{{ asset('storage/'.$siteSettings->logo_path) }}" alt="{{ $siteSettings->site_name }}" height="36">
                @else
                    {{ $siteSettings->site_name }}
                @endif
            </a>
```

Also replace the `<title>` tag's fallback (currently `config('app.name')`) so the browser tab reflects the configured name too:

```blade
    <title>@yield('title', $siteSettings->site_name ?? config('app.name'))</title>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=SettingsTest`
Expected: PASS

- [ ] **Step 9: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — every existing page extends `layouts.app`, so confirm nothing else broke now that `$siteSettings` is required on every render.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_14_180000_create_settings_table.php \
        app/Models/Setting.php app/Policies/SettingPolicy.php \
        app/Filament/Pages/ManageSettings.php \
        resources/views/filament/pages/manage-settings.blade.php \
        app/Providers/AppServiceProvider.php \
        resources/views/layouts/app.blade.php \
        tests/Feature/SettingsTest.php
git commit -m "Add admin-editable site branding (logo, site name)"
```

---

## Task 2: Live-category mega-menu on the header

**Files:**
- Create: `database/migrations/2026_07_14_180100_add_show_category_menu_to_nav_items_table.php`
- Modify: `app/Models/NavItem.php`
- Modify: `app/Filament/Resources/NavItemResource.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/MegaMenuTest.php`

**Interfaces:**
- Produces: `$topLevelCategories` on `layouts.app` — a collection of top-level published categories with their published children eager-loaded, for the mega-menu.
- Produces: `NavItem::show_category_menu` (boolean, default `false`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_a_nav_item_with_the_mega_menu_flag_shows_the_live_category_tree(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);
        $root = Category::factory()->create(['parent_id' => null, 'name' => 'Fiber Optic Cable', 'slug' => 'fiber-optic-cable', 'status' => 'published']);
        Category::factory()->create(['parent_id' => $root->id, 'name' => 'Aerial', 'slug' => 'aerial', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Fiber Optic Cable');
        $response->assertSee('Aerial');
        $response->assertSee('/products/fiber-optic-cable/aerial', escape: false);
    }

    public function test_a_draft_category_never_appears_in_the_mega_menu(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);
        Category::factory()->create(['parent_id' => null, 'name' => 'Hidden Draft Category', 'status' => 'draft']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Hidden Draft Category');
    }

    public function test_a_nav_item_without_the_flag_keeps_its_own_manual_children(): void
    {
        $parent = NavItem::factory()->create(['label' => 'Company', 'url' => '/company', 'location' => 'header', 'parent_id' => null, 'show_category_menu' => false]);
        NavItem::factory()->create(['label' => 'About Us', 'url' => '/about', 'location' => 'header', 'parent_id' => $parent->id]);
        Category::factory()->create(['parent_id' => null, 'name' => 'Should Not Appear Here', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('About Us');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MegaMenuTest`
Expected: FAIL — `show_category_menu` column and mega-menu rendering don't exist yet.

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
        Schema::table('nav_items', function (Blueprint $table) {
            $table->boolean('show_category_menu')->default(false)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->dropColumn('show_category_menu');
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/NavItem.php`, update `$fillable` and add a cast:

```php
    protected $fillable = [
        'label', 'url', 'location', 'parent_id', 'sort_order', 'show_category_menu',
    ];

    protected $casts = [
        'show_category_menu' => 'boolean',
    ];
```

- [ ] **Step 5: Add the toggle to NavItemResource**

In `app/Filament/Resources/NavItemResource.php`, add the import:

```php
use Filament\Forms\Components\Toggle;
```

Add this field to the `form()` schema, right after the `location` `Select`:

```php
            Toggle::make('show_category_menu')
                ->label('Show live category mega-menu')
                ->helperText('When enabled, this item\'s dropdown shows the full published category tree instead of any manually-added sub-items below. Only meaningful for a top-level header item.')
                ->live()
                ->visible(fn (callable $get) => $get('location') === 'header' && ! $get('parent_id')),
```

- [ ] **Step 6: Wire the view composer**

In `app/Providers/AppServiceProvider.php`, add the `Category` import and extend the same composer closure from Task 1:

```php
use App\Models\Category;
```

```php
            $view->with('topLevelCategories', Category::query()
                ->whereNull('parent_id')
                ->where('status', 'published')
                ->with(['children' => fn ($query) => $query->where('status', 'published')->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get());
```

- [ ] **Step 7: Render the mega-menu in the layout**

In `resources/views/layouts/app.blade.php`, replace the header nav loop:

```blade
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
```

with:

```blade
                <ul class="navbar-nav me-auto">
                    @foreach ($headerNavItems as $item)
                        @if ($item->show_category_menu)
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                                <div class="dropdown-menu mega-menu p-3">
                                    <div class="row">
                                        @foreach ($topLevelCategories as $topCategory)
                                            <div class="col-6 col-md-3 mb-3">
                                                <a href="{{ url('/products/'.$topCategory->path()) }}" class="fw-bold text-dark text-decoration-none d-block mb-2">{{ $topCategory->name }}</a>
                                                @foreach ($topCategory->children as $sub)
                                                    <a href="{{ url('/products/'.$sub->path()) }}" class="d-block text-muted text-decoration-none small mb-1">{{ $sub->name }}</a>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </li>
                        @elseif ($item->children->isNotEmpty())
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
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=MegaMenuTest`
Expected: PASS

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS — in particular confirm `NavigationRenderingTest` (from the Content Pages & Navigation phase) still passes unchanged, since the non-mega-menu dropdown branch is untouched logic, just moved into an `@elseif`.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_14_180100_add_show_category_menu_to_nav_items_table.php \
        app/Models/NavItem.php app/Filament/Resources/NavItemResource.php \
        app/Providers/AppServiceProvider.php resources/views/layouts/app.blade.php \
        tests/Feature/MegaMenuTest.php
git commit -m "Add live-category mega-menu option for header nav items"
```

---

## Task 3: Hero carousel content block (image or video slides, show/hide)

**Files:**
- Modify: `app/Filament/Resources/PageResource.php`
- Create: `resources/views/blocks/hero_carousel.blade.php`
- Test: `tests/Feature/HeroCarouselBlockTest.php`

**Interfaces:**
- Consumes: `pages/show.blade.php`'s existing `@includeIf('blocks.'.$block['type'], ['data' => ..., 'blockKey' => $loop->index])` mechanism (Content Pages & Navigation phase) — no changes needed there.
- Produces: a new Builder block type `hero_carousel`, additive alongside the existing `hero` block.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroCarouselBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_carousel_renders_only_active_slides(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [
                    ['media_type' => 'image', 'heading' => 'Visible Slide', 'active' => true],
                    ['media_type' => 'image', 'heading' => 'Hidden Slide', 'active' => false],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Visible Slide');
        $response->assertDontSee('Hidden Slide');
    }

    public function test_a_video_slide_renders_a_video_element(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [
                    ['media_type' => 'video', 'video_url' => 'https://example.com/promo.mp4', 'heading' => 'Watch This', 'active' => true],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://example.com/promo.mp4', escape: false);
    }

    public function test_two_carousels_on_one_page_get_unique_dom_ids(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [['media_type' => 'image', 'heading' => 'First', 'active' => true]]]],
                ['type' => 'hero_carousel', 'data' => ['slides' => [['media_type' => 'image', 'heading' => 'Second', 'active' => true]]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="heroCarousel0"', escape: false);
        $response->assertSee('id="heroCarousel1"', escape: false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=HeroCarouselBlockTest`
Expected: FAIL — block type and partial don't exist yet.

- [ ] **Step 3: Add the Builder block**

In `app/Filament/Resources/PageResource.php`, add the imports:

```php
use Filament\Forms\Components\Toggle;
```

Add this block to the `Builder::make('content')->blocks([...])` array, right after the existing `hero` block:

```php
                    Block::make('hero_carousel')
                        ->label('Hero Carousel')
                        ->schema([
                            Repeater::make('slides')
                                ->schema([
                                    Select::make('media_type')
                                        ->options(['image' => 'Image', 'video' => 'Video'])
                                        ->default('image')
                                        ->live()
                                        ->required(),
                                    FileUpload::make('image')
                                        ->image()
                                        ->directory('page-blocks')
                                        ->visible(fn (callable $get) => $get('media_type') === 'image'),
                                    TextInput::make('video_url')
                                        ->label('Video URL (direct .mp4 link)')
                                        ->url()
                                        ->visible(fn (callable $get) => $get('media_type') === 'video'),
                                    TextInput::make('heading')->required(),
                                    TextInput::make('subheading'),
                                    TextInput::make('cta_label'),
                                    TextInput::make('cta_url'),
                                    Toggle::make('active')
                                        ->label('Show this slide')
                                        ->default(true),
                                ])
                                ->required()
                                ->minItems(1),
                        ]),
```

- [ ] **Step 4: Add the render partial**

```blade
{{-- resources/views/blocks/hero_carousel.blade.php --}}
@php
    $slides = collect($data['slides'] ?? [])->filter(fn ($slide) => $slide['active'] ?? true)->values();
    $carouselId = 'heroCarousel'.($blockKey ?? 0);
@endphp
@if ($slides->isNotEmpty())
    <div id="{{ $carouselId }}" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-indicators">
            @foreach ($slides as $index => $slide)
                <button type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide-to="{{ $index }}"
                    @if ($index === 0) class="active" aria-current="true" @endif
                    aria-label="Slide {{ $index + 1 }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner rounded-3">
            @foreach ($slides as $index => $slide)
                <div class="carousel-item @if ($index === 0) active @endif">
                    @if (($slide['media_type'] ?? 'image') === 'video' && !empty($slide['video_url']))
                        <video class="d-block w-100" style="max-height: 480px; object-fit: cover;" autoplay muted loop playsinline>
                            <source src="{{ $slide['video_url'] }}">
                        </video>
                    @elseif (!empty($slide['image']))
                        <img src="{{ asset('storage/'.$slide['image']) }}" class="d-block w-100" style="max-height: 480px; object-fit: cover;" alt="{{ $slide['heading'] ?? '' }}">
                    @endif
                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded-3 p-3">
                        @if (!empty($slide['heading']))
                            <h2>{{ $slide['heading'] }}</h2>
                        @endif
                        @if (!empty($slide['subheading']))
                            <p>{{ $slide['subheading'] }}</p>
                        @endif
                        @if (!empty($slide['cta_label']) && !empty($slide['cta_url']))
                            <a href="{{ $slide['cta_url'] }}" class="btn btn-primary">{{ $slide['cta_label'] }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @if ($slides->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        @endif
    </div>
@endif
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=HeroCarouselBlockTest`
Expected: PASS

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS — confirm the existing `hero` block and `PageBlockRenderingTest` are untouched.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/PageResource.php \
        resources/views/blocks/hero_carousel.blade.php \
        tests/Feature/HeroCarouselBlockTest.php
git commit -m "Add hero carousel block (image/video slides, per-slide show/hide)"
```

---

## Task 4: Content strip block (image + text)

**Files:**
- Modify: `app/Filament/Resources/PageResource.php`
- Create: `resources/views/blocks/content_strip.blade.php`
- Test: `tests/Feature/ContentStripBlockTest.php`

**Interfaces:**
- Produces: a new Builder block type `content_strip`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentStripBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_content_strip_renders_heading_and_body(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Why Buy From Us',
                    'body' => '<p>Quality-tested inventory, fast quotes.</p>',
                    'image_position' => 'left',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Why Buy From Us');
        $response->assertSee('Quality-tested inventory, fast quotes.', escape: false);
    }

    public function test_image_position_right_puts_the_text_column_first_in_the_markup(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Right Positioned',
                    'body' => '<p>Body text.</p>',
                    'image' => 'page-blocks/example.jpg',
                    'image_position' => 'right',
                ]],
            ],
        ]);

        $response = $this->get('/');
        $html = $response->getContent();

        $headingPosition = strpos($html, 'Right Positioned');
        $imagePosition = strpos($html, 'page-blocks/example.jpg');

        $response->assertOk();
        $this->assertLessThan($imagePosition, $headingPosition, 'Expected the text column to precede the image column in the markup when image_position is right.');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ContentStripBlockTest`
Expected: FAIL — block type and partial don't exist yet.

- [ ] **Step 3: Add the Builder block**

In `app/Filament/Resources/PageResource.php`, add this block to the `Builder::make('content')->blocks([...])` array, right after the `hero_carousel` block added in Task 3:

```php
                    Block::make('content_strip')
                        ->label('Content Strip (Image + Text)')
                        ->schema([
                            TextInput::make('heading'),
                            RichEditor::make('body')->required(),
                            FileUpload::make('image')
                                ->image()
                                ->directory('page-blocks'),
                            Select::make('image_position')
                                ->options(['left' => 'Image Left', 'right' => 'Image Right'])
                                ->default('left')
                                ->required(),
                        ]),
```

- [ ] **Step 4: Add the render partial**

```blade
{{-- resources/views/blocks/content_strip.blade.php --}}
@php
    $imageFirst = ($data['image_position'] ?? 'left') === 'left';
@endphp
<div class="row align-items-center g-4 mb-4">
    @if ($imageFirst && !empty($data['image']))
        <div class="col-md-6">
            <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid rounded-3" alt="{{ $data['heading'] ?? '' }}">
        </div>
    @endif
    <div class="col-md-6">
        @if (!empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (!empty($data['body']))
            <div>{!! $data['body'] !!}</div>
        @endif
    </div>
    @if (! $imageFirst && !empty($data['image']))
        <div class="col-md-6">
            <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid rounded-3" alt="{{ $data['heading'] ?? '' }}">
        </div>
    @endif
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ContentStripBlockTest`
Expected: PASS

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/PageResource.php \
        resources/views/blocks/content_strip.blade.php \
        tests/Feature/ContentStripBlockTest.php
git commit -m "Add content strip block (image + text, configurable image side)"
```

---

## Task 5: Alibaba-inspired visual redesign

**Files:**
- Create: `public/css/site.css`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/SiteStylesheetTest.php`

**Interfaces:**
- Consumes: nothing new — purely presentational, applies on top of the existing Bootstrap 5 CDN load.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteStylesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_layout_links_the_custom_stylesheet(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('css/site.css', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiteStylesheetTest`
Expected: FAIL — stylesheet not linked yet.

- [ ] **Step 3: Add the stylesheet**

```css
/* public/css/site.css */

:root {
    --bs-primary: #ff6a00;
    --bs-primary-rgb: 255, 106, 0;
    --bs-link-color: #ff6a00;
    --bs-link-hover-color: #e65f00;
    --bs-body-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.btn-primary {
    background-color: #ff6a00;
    border-color: #ff6a00;
}

.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active {
    background-color: #e65f00 !important;
    border-color: #e65f00 !important;
}

.btn-outline-primary {
    color: #ff6a00;
    border-color: #ff6a00;
}

.btn-outline-primary:hover {
    background-color: #ff6a00;
    border-color: #ff6a00;
}

.navbar {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.4rem;
    color: #ff6a00 !important;
}

.nav-link:hover {
    color: #ff6a00 !important;
}

.card {
    border: 1px solid #eee;
    border-radius: 10px;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.mega-menu {
    min-width: 640px;
}

footer {
    background-color: #1c1c1c !important;
}

footer a {
    color: #cfcfcf;
    text-decoration: none;
}

footer a:hover {
    color: #ff6a00;
}

.text-primary {
    color: #ff6a00 !important;
}

.breadcrumb-item a {
    color: #ff6a00;
}
```

- [ ] **Step 4: Link the stylesheet in the layout**

In `resources/views/layouts/app.blade.php`, add this line right after the Bootstrap CDN `<link>`:

```blade
    <link href="{{ asset('css/site.css') }}" rel="stylesheet">
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SiteStylesheetTest`
Expected: PASS

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add public/css/site.css resources/views/layouts/app.blade.php tests/Feature/SiteStylesheetTest.php
git commit -m "Add Alibaba-inspired visual theme (orange accent, card/nav polish)"
```

---

## Task 6: Demo content — update seeded home page and header nav

**Files:**
- Modify: `database/seeders/PageSeeder.php`
- Modify: `database/seeders/NavItemSeeder.php`

**Interfaces:**
- Consumes: `hero_carousel` (Task 3), `content_strip` (Task 4), existing `featured_products` block, `show_category_menu` (Task 2). Demonstrates every new feature on the actual seeded demo data so a fresh `migrate:fresh --seed` shows the full redesign, not just the plumbing.

- [ ] **Step 1: Read the current seeders**

Run: `cat database/seeders/PageSeeder.php` and `cat database/seeders/NavItemSeeder.php` to see the exact current `firstOrCreate` structure before editing — this task modifies existing seed data, so match the established `firstOrCreate` idempotency pattern exactly (don't switch to `create`, which would duplicate rows on repeated `--seed` runs against a MySQL dev DB that already has data, unlike the fresh in-memory SQLite test DB).

- [ ] **Step 2: Update the home page's content array**

In `database/seeders/PageSeeder.php`, change the `home` page's `content` value (keep the `firstOrCreate` wrapper and `slug`/`status`/`meta_*` fields exactly as they are — only replace the `content` array) to:

```php
[
    ['type' => 'hero_carousel', 'data' => ['slides' => [
        [
            'media_type' => 'image',
            'heading' => 'Sourcing Cable & Wire, Simplified',
            'subheading' => 'Browse our catalog and request a quote — no account required.',
            'cta_label' => 'Browse Products',
            'cta_url' => '/products',
            'active' => true,
        ],
    ]]],
    ['type' => 'content_strip', 'data' => [
        'heading' => 'Why Buy From Us',
        'body' => '<p>Every listing is reviewed and priced by our sourcing team before it goes live, so you always know you\'re getting quality-tested inventory at a fair price.</p>',
        'image_position' => 'left',
    ]],
]
```

(Note: no `product_ids` for a `featured_products` block here — leave that block out of the seeded demo unless the dev DB already has published products with known IDs at seed time; `CatalogSeeder` runs before `PageSeeder` in `DatabaseSeeder`, so if you want to demo `featured_products` too, look up real product IDs from `CatalogSeeder`'s seeded products first rather than hardcoding IDs that may not exist.)

- [ ] **Step 3: Add the mega-menu flag to the seeded "Products" nav item**

In `database/seeders/NavItemSeeder.php`, find the `firstOrCreate` call that creates the header "Products" nav item and add `'show_category_menu' => true` to its attributes array (alongside `label`, `url`, `location`, `sort_order` — whatever the existing structure has).

- [ ] **Step 4: Verify against a fresh seed**

Run: `php artisan migrate:fresh --seed --database=sqlite --force` is not applicable here (seeders are exercised by the feature test suite, not run standalone against sqlite in CI) — instead confirm via the full test suite, since `PageSeeder`/`NavItemSeeder` aren't directly unit-tested but are exercised indirectly by any test that calls `$this->seed()`. Search for existing seeder-invoking tests:

Run: `grep -rl "seed(" tests/Feature/`

If any test seeds the database, run that specific test file to confirm the updated seed data doesn't break it. If none do, this step is a no-op — proceed to Step 5.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS — no test should depend on the home page's literal prior `hero` block content, since `PageSeeder` output isn't used by any assertion in the existing suite (each test creates its own `Page::factory()` rows). If something does fail here, read the failing test before changing seeder content further — it means an existing test has an undocumented dependency on the old seed shape that this task's brief didn't anticipate.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PageSeeder.php database/seeders/NavItemSeeder.php
git commit -m "Update seeded home page and nav to demo the new blocks and mega-menu"
```

---

## Task 7: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: All tests pass (existing suite + every test added in Tasks 1-6), 0 failures.

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: Only the files created/modified by Tasks 1-6 are listed. Discard any benign CRLF-only `public/css/filament/*`/`public/js/filament/*` noise per the pattern documented in `CLAUDE.md` — but do **not** discard `public/css/site.css`, which is a real new file from Task 5, not vendor noise.

- [ ] **Step 3: Confirm the Settings page is genuinely role-gated**

Run: `php artisan test --filter=SettingsTest`
Expected: PASS, specifically re-confirming `test_a_content_editor_cannot_access_the_settings_page`.

- [ ] **Step 4: Confirm no regression in existing Filament panel access tests**

Run: `php artisan test --filter=SellerPanelAccessTest`
Run: `php artisan test --filter=StaffPanelAccessTest`
Expected: PASS — the new `ManageSettings` page and `settings`/`nav_items` schema changes must not affect the separate `staff`/`seller` guards.

- [ ] **Step 5: Manual visual check (cannot be automated)**

Start the dev server if not already running (`php artisan serve`) and, in a browser:
- Visit `/` — confirm the hero carousel renders, the content strip renders, the orange theme is applied, and the header shows the configured site name/logo.
- Hover/click "Products" in the header — confirm the mega-menu shows real categories in columns.
- Visit `/admin/manage-settings` as the seeded admin (`admin@example.com` / `password`) — upload a logo, save, and confirm it appears in the header on the next public-page load.
- Report back to the user what you saw; this step cannot be verified by the automated test suite alone, per this project's UI-testing convention.
