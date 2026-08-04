# Homepage Modernist Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the "Modernist" visual design system to the public Homepage —
new utility bar/header/footer styling, and a new set of Page-Builder content
blocks (hero banner, trust badges, restyled category/product grids, deals
banner, RFQ form, newsletter signup) — reviewable at `localhost:8000`, with no
production deploy until the user explicitly confirms after review.

**Architecture:** Additive to the existing Blade + Bootstrap 5 (CDN) public
site and the existing block-based `Page`/`Builder` CMS system — no new
frontend framework, no Vite build step. A new static stylesheet
(`public/css/modernist.css`, plain `<link>`, no build step, matching how
`site.css` is already loaded) supplies design tokens and `md`-prefixed
component classes layered on top of Bootstrap. New homepage sections are new
or extended Filament `Builder` block types on the existing `Page` model, kept
independently editable via `/admin`, exactly like `hero_carousel` and
`content_strip` before them. The mega-menu's Blade logic, data, and Bootstrap
dropdown JS in `layouts/app.blade.php` are not touched anywhere in this plan.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3, Blade + Bootstrap 5 (CDN,
unchanged), MySQL (dev) / SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **No Vite/build-step CSS.** `public/css/modernist.css` is a plain static
  file, linked directly in `layouts/app.blade.php`'s `<head>`, same as
  `site.css` — no `resources/css` + `@vite()`.
- **Mega-menu markup, data, and JS are never modified.** The
  `show_category_menu` dropdown branch (currently `layouts/app.blade.php`
  lines 29-44), the simple-dropdown/plain-link branches next to it (lines
  45-56), and the `View::composer('layouts.app', ...)` data they consume in
  `AppServiceProvider::boot()` keep their exact structure. Only CSS coming
  from `modernist.css`, scoped under a new `.md-theme` class on `<body>`, may
  change how they *look*.
- **New component classes are `md`-prefixed** (`.md-btn`, `.md-card`, etc.) to
  avoid colliding with Bootstrap's own classes or `public/css/site.css`'s
  existing `.btn-primary` override (already redefined site-wide as orange
  `#ff6a00`).
- **No supplier/seller name renders anywhere on a public card.** Per
  `CLAUDE.md`: "Seller identity is never rendered on any public page."
- **Existing block types (`hero`, `hero_carousel`, `content_strip`,
  `rich_text`, `resource_list`, `faq`) are not removed, renamed, or
  repurposed** — new visual needs get new block types
  (`hero_banner`/`trust_badges`/`deals_banner`/`newsletter_signup`), matching
  this codebase's established "additive, not replacing" convention (see
  `docs/superpowers/plans/2026-07-14-premium-storefront-redesign.md`).
- **Tests run against SQLite in-memory** (`phpunit.xml`), never the dev MySQL
  database. Use `php artisan migrate` (never `migrate:fresh`) against the dev
  database, per `CLAUDE.md`.
- Design tokens (exact values, from the approved design handoff spec):
  `--color-bg:#f3f2f2`, `--color-text:#201e1d`, `--color-accent:#ec3013`,
  `--color-accent-700:#b82000`, `--color-neutral-100:#f3f2f2`,
  `--color-neutral-700:#666`, `--color-neutral-900:#1a1a1a`,
  `--color-divider:#e0e0e0`, `--font-heading`/`--font-body: 'Archivo',
  sans-serif`, `--space-2:4px` through `--space-9:48px` (see Task 1),
  `--radius-0:0px`, `--shadow-sm:0 1px 3px rgba(0,0,0,.1)`,
  `--shadow-md:0 4px 12px rgba(0,0,0,.15)`.

## Context for the implementer

Read `docs/superpowers/specs/2026-08-04-homepage-modernist-redesign-design.md`
first — it has the full rationale for every decision below.

Existing pieces already in place (do not re-build these):
- `app/Models/Page.php` — `content` is an `array`-cast JSON column; fillable
  includes `content`. `isPublished()` checks `status === 'published'`.
- `resources/views/pages/show.blade.php` — renders each `content` entry via
  `@includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? [],
  'blockKey' => $loop->index])`. Any new block that emits a DOM `id=` must
  incorporate `$blockKey` to avoid collisions when the same block type
  appears twice on one page (see `rfq_form_embed.blade.php`'s
  `'-embed-'.($blockKey ?? 0)` pattern).
- `app/Filament/Resources/PageResource.php` — `Builder::make('content')
  ->blocks([...])` (lines 46-154) is the single place new block types are
  registered; `Category`, `Product`, and all the `Filament\Forms\Components\*`
  classes used below (`TextInput`, `Textarea`, `RichEditor`, `FileUpload`,
  `Repeater`, `Select`, `Toggle`) are already imported at the top of this
  file.
- `resources/views/blocks/featured_categories.blade.php` and
  `featured_products.blade.php` — existing blocks with `category_ids`/
  `product_ids` schemas; both already filter to `status === 'published'` and
  preserve the editor's chosen order.
- `resources/views/blocks/rfq_form_embed.blade.php` — already includes
  `partials/quote-request-form-fields.blade.php` (the full, real RFQ form —
  all required fields, CSRF, validation errors, optional reCAPTCHA, posts to
  `route('quote-requests.store')`). Do not touch that partial; it's also used
  by the product-detail-page modal.
- `app/Support/CategoryHierarchy::descendantAndSelfIds(Category $category):
  array` — returns a category's own id plus every descendant's id. Works
  correctly for a leaf category too (returns just its own id), so it's safe
  to call unconditionally rather than branching on whether the category has
  children.
- `App\Models\Product` — `fillable` includes `quantity` (used as MOQ) and
  `price_display` (Admin-set free text); `primaryImage(): ?ProductImage`.
- `resources/views/components/product-thumbnail.blade.php` — `@props(['path'
  => null, 'alt' => ''])`, renders a fixed 132×132 `<img>`. Do not modify.
- `app/Providers/AppServiceProvider.php::boot()` — the single
  `View::composer('layouts.app', ...)` closure that supplies
  `$headerNavItems`, `$footerNavItems`, `$siteSettings`, `$topLevelCategories`
  to every page. Add to this same closure; do not create a second composer.
- Existing tests that assert on `layouts.app` output and **must still pass
  unchanged** after this plan: `tests/Feature/MegaMenuTest.php`,
  `tests/Feature/AuthChromeTest.php`,
  `tests/Feature/NavigationRenderingTest.php`,
  `tests/Feature/SiteStylesheetTest.php`,
  `tests/Feature/SellerRegisterLinkTest.php`,
  `tests/Feature/PageBlockRenderingTest.php`,
  `tests/Feature/ContentStripBlockTest.php`,
  `tests/Feature/HeroCarouselBlockTest.php`.
- `database/seeders/PageSeeder.php` — seeds the `home` page via
  `Page::query()->firstOrCreate(['slug' => 'home'], [...])`. Because this uses
  `firstOrCreate`, editing this file alone does **not** change the row already
  sitting in the dev MySQL database — Task 10 includes an explicit tinker step
  to update that existing row so `localhost:8000` actually reflects the new
  design.

---

## Task 1: Modernist design tokens + Archivo font, loaded (not yet used anywhere)

**Files:**
- Create: `public/css/modernist.css`
- Modify: `resources/views/layouts/app.blade.php:1-12` (head section)
- Test: `tests/Feature/ModernistStylesheetTest.php`

**Interfaces:**
- Produces: CSS custom properties every later task relies on —
  `--color-bg`, `--color-text`, `--color-accent`, `--color-accent-700`,
  `--color-neutral-100/700/900`, `--color-divider`, `--font-heading`,
  `--font-body`, `--space-2` through `--space-9`, `--radius-0`,
  `--shadow-sm`, `--shadow-md`. Component classes: `.md-btn`,
  `.md-btn-primary`, `.md-btn-secondary`, `.md-btn-ghost`, `.md-btn-block`,
  `.md-tag`, `.md-tag-accent`, `.md-tag-outline`, `.md-card`, `.md-elev-sm`,
  `.md-elev-md`, `.md-hr`, `.md-grayscale`. All scoped for typography under a
  `.md-theme` class (added to `<body>` in Task 2 — this task only defines the
  rule, Task 2 applies the class).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModernistStylesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_layout_links_the_modernist_stylesheet(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('css/modernist.css', escape: false);
    }

    public function test_the_stylesheet_defines_the_modernist_design_tokens(): void
    {
        $css = file_get_contents(public_path('css/modernist.css'));

        $this->assertStringContainsString('--color-accent: #ec3013', $css);
        $this->assertStringContainsString('--color-bg: #f3f2f2', $css);
        $this->assertStringContainsString('--font-heading', $css);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ModernistStylesheetTest`
Expected: FAIL — `public/css/modernist.css` doesn't exist yet and isn't linked.

- [ ] **Step 3: Create `public/css/modernist.css`**

```css
/* public/css/modernist.css — "Modernist" design system tokens + components.
   Layered on top of Bootstrap 5 (still CDN-loaded); md-prefixed to avoid
   colliding with Bootstrap or site.css's existing class overrides. */

:root {
    --color-bg: #f3f2f2;
    --color-text: #201e1d;
    --color-accent: #ec3013;
    --color-neutral-100: #f3f2f2;
    --color-neutral-700: #666;
    --color-neutral-900: #1a1a1a;
    --color-divider: #e0e0e0;
    --color-accent-700: #b82000;

    --font-heading: 'Archivo', sans-serif;
    --font-body: 'Archivo', sans-serif;

    --space-2: 4px;
    --space-3: 8px;
    --space-4: 12px;
    --space-5: 16px;
    --space-6: 24px;
    --space-7: 32px;
    --space-8: 40px;
    --space-9: 48px;

    --radius-0: 0px;

    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.md-theme {
    font-family: var(--font-body);
    color: var(--color-text);
}

.md-theme h1, .md-theme h2, .md-theme h3, .md-theme h4 {
    font-family: var(--font-heading);
    font-weight: 800;
}

/* Buttons */
.md-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 15px;
    padding: var(--space-4) var(--space-6);
    border: 2px solid transparent;
    border-radius: var(--radius-0);
    text-decoration: none;
    cursor: pointer;
    transition: background-color .15s, color .15s, border-color .15s;
}

.md-btn-primary {
    background: var(--color-accent);
    color: #fff;
    border-color: var(--color-accent);
}

.md-btn-primary:hover, .md-btn-primary:focus {
    background: var(--color-accent-700);
    border-color: var(--color-accent-700);
    color: #fff;
}

.md-btn-secondary {
    background: transparent;
    color: var(--color-text);
    border-color: var(--color-text);
}

.md-btn-secondary:hover, .md-btn-secondary:focus {
    background: var(--color-text);
    color: var(--color-bg);
}

.md-btn-ghost {
    background: transparent;
    color: var(--color-accent-700);
    border-color: transparent;
}

.md-btn-ghost:hover, .md-btn-ghost:focus {
    color: var(--color-accent);
    text-decoration: underline;
}

.md-btn-block {
    display: flex;
    width: 100%;
}

/* Tags */
.md-tag {
    display: inline-block;
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: var(--space-2) var(--space-4);
    border: 2px solid var(--color-divider);
    border-radius: var(--radius-0);
    color: var(--color-text);
}

.md-tag-accent {
    background: var(--color-accent);
    border-color: var(--color-accent);
    color: #fff;
}

.md-tag-outline {
    background: transparent;
    border-color: var(--color-accent-700);
    color: var(--color-accent-700);
}

/* Cards */
.md-card {
    display: block;
    background: #fff;
    border: 2px solid var(--color-divider);
    border-radius: var(--radius-0);
    color: var(--color-text);
}

.md-elev-sm { box-shadow: var(--shadow-sm); }
.md-elev-md { box-shadow: var(--shadow-md); }

/* Dividers */
.md-hr {
    border: none;
    border-top: 2px solid var(--color-divider);
    margin: var(--space-6) 0;
}

/* Image treatment */
.md-grayscale {
    filter: grayscale(100%);
    transition: filter .2s;
}

.md-grayscale:hover {
    filter: grayscale(0%);
}
```

- [ ] **Step 4: Link the stylesheet and the Archivo font in the layout head**

In `resources/views/layouts/app.blade.php`, immediately after the existing
Bootstrap CSS `<link>` (currently line 10) and before the `site.css` link
(currently line 11), add the Google Fonts include; keep the existing two
lines as-is and add the new stylesheet link after `site.css`:

```blade
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/site.css') }}" rel="stylesheet">
    <link href="{{ asset('css/modernist.css') }}" rel="stylesheet">
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ModernistStylesheetTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add public/css/modernist.css resources/views/layouts/app.blade.php tests/Feature/ModernistStylesheetTest.php
git commit -m "feat: add Modernist design token stylesheet"
```

---

## Task 2: Utility bar + header/footer restyle (global chrome, mega-menu untouched)

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (body tag, new utility bar
  row, footer)
- Modify: `app/Providers/AppServiceProvider.php` (add `$helpCenterPage` to the
  existing `layouts.app` composer)
- Modify: `public/css/modernist.css` (append chrome-scoped rules)
- Test: `tests/Feature/UtilityBarTest.php`

**Interfaces:**
- Consumes: `.md-theme`, `--color-*` tokens from Task 1.
- Produces: `$helpCenterPage` (nullable `Page`) available on `layouts.app`,
  consumed only by the utility bar markup added in this task.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_the_utility_bar_links_to_seller_registration(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Become a Seller');
        $response->assertSee(route('seller.register'), escape: false);
    }

    public function test_the_utility_bar_shows_a_help_center_link_when_that_page_exists(): void
    {
        Page::factory()->create(['slug' => 'help-center', 'status' => 'published', 'title' => 'Help Center']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Help Center');
        $response->assertSee('/help-center', escape: false);
    }

    public function test_the_utility_bar_omits_the_help_center_link_when_no_such_page_exists(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Help Center');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=UtilityBarTest`
Expected: FAIL — no utility bar markup exists yet.

- [ ] **Step 3: Add `$helpCenterPage` to the view composer**

In `app/Providers/AppServiceProvider.php`, add the import and one line inside
the existing `View::composer('layouts.app', function ($view) { ... })`
closure (after the `$topLevelCategories` assignment):

```php
use App\Models\Page;
```

```php
            $view->with('helpCenterPage', Page::query()
                ->where('slug', 'help-center')
                ->where('status', 'published')
                ->first());
```

- [ ] **Step 4: Add the utility bar and `md-theme` body class**

In `resources/views/layouts/app.blade.php`, change line 13 and insert the new
row right after it:

```blade
<body class="md-theme">
    <div class="md-utility-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <span>Ship to: India | English</span>
            <div class="d-flex gap-3">
                @if ($helpCenterPage)
                    <a href="{{ url('/'.$helpCenterPage->slug) }}">Help Center</a>
                @endif
                <a href="{{ route('seller.register') }}">Become a Seller</a>
            </div>
        </div>
    </div>
```

Leave the `<nav class="navbar ...">` block and everything inside it
(including the entire mega-menu `@foreach ($headerNavItems as $item)` loop)
exactly as it is — do not edit those lines.

- [ ] **Step 5: Append chrome-scoped CSS**

Append to `public/css/modernist.css`:

```css
/* Global chrome: utility bar, header, footer. Scoped under .md-theme so it
   never leaks onto anything outside the public layout (e.g. Filament). */

.md-theme .md-utility-bar {
    background: var(--color-neutral-900);
    color: var(--color-bg);
    font-size: 12px;
    padding: var(--space-3) 0;
}

.md-theme .md-utility-bar a {
    color: var(--color-bg);
    text-decoration: none;
}

.md-theme .md-utility-bar a:hover {
    color: var(--color-accent);
}

.md-theme .navbar {
    background: var(--color-bg) !important;
    border-bottom: 2px solid var(--color-divider) !important;
    box-shadow: none;
}

.md-theme .navbar-brand {
    font-family: var(--font-heading);
    font-weight: 800;
    color: var(--color-text) !important;
}

.md-theme .nav-link {
    font-family: var(--font-heading);
    font-weight: 600;
    color: var(--color-text) !important;
}

.md-theme .nav-link:hover {
    color: var(--color-accent) !important;
}

.md-theme footer {
    background: var(--color-neutral-900);
    color: var(--color-bg);
    border-top: none;
}

.md-theme footer a {
    color: var(--color-bg);
}

.md-theme footer a:hover {
    color: var(--color-accent);
}
```

- [ ] **Step 6: Run the new tests, then the full existing chrome-dependent suite**

Run: `php artisan test --filter=UtilityBarTest`
Expected: PASS

Run: `php artisan test --filter=MegaMenuTest`
Run: `php artisan test --filter=AuthChromeTest`
Run: `php artisan test --filter=NavigationRenderingTest`
Run: `php artisan test --filter=SiteStylesheetTest`
Run: `php artisan test --filter=SellerRegisterLinkTest`
Expected: all PASS unchanged — confirms the mega-menu, auth links, nav items,
`site.css` link, and seller-login link all still render exactly as before.

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php app/Providers/AppServiceProvider.php public/css/modernist.css tests/Feature/UtilityBarTest.php
git commit -m "feat: restyle utility bar, header, and footer with Modernist chrome"
```

---

## Task 3: `hero_banner` block (new)

**Files:**
- Create: `resources/views/blocks/hero_banner.blade.php`
- Modify: `app/Filament/Resources/PageResource.php` (register the block)
- Test: `tests/Feature/HeroBannerBlockTest.php`

**Interfaces:**
- Produces: block type `hero_banner` with data shape `{tag, heading, body,
  search_placeholder, cta_primary_label, cta_primary_url,
  cta_secondary_label, cta_secondary_url, image}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroBannerBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_banner_renders_its_copy_and_both_ctas(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_banner', 'data' => [
                    'tag' => 'B2B Sourcing Marketplace',
                    'heading' => 'Sourcing Cable & Wire — and Everything Else — Simplified',
                    'body' => 'Browse thousands of verified listings and request a quote in minutes.',
                    'search_placeholder' => 'Search for item by keyword or product number',
                    'cta_primary_label' => 'Browse Products',
                    'cta_primary_url' => '/products',
                    'cta_secondary_label' => 'Request a Quote',
                    'cta_secondary_url' => '/#rfq',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('B2B Sourcing Marketplace');
        $response->assertSee('Sourcing Cable & Wire'); // assertSee escapes this for us — matches Blade's auto-escaped output
        $response->assertSee('Browse Products');
        $response->assertSee('Request a Quote');
        $response->assertSee('/products', escape: false);
        $response->assertSee('/#rfq', escape: false);
    }

    public function test_the_hero_search_form_submits_to_the_real_catalog_search_route(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_banner', 'data' => ['heading' => 'Test Heading', 'search_placeholder' => 'Search']],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('action="'.route('catalog.search').'"', escape: false);
        $response->assertSee('name="q"', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HeroBannerBlockTest`
Expected: FAIL — `blocks.hero_banner` view doesn't exist.

- [ ] **Step 3: Create the block view**

```blade
{{-- resources/views/blocks/hero_banner.blade.php --}}
<div class="md-hero mb-4 py-5">
    <div class="row align-items-center g-5">
        <div class="col-md-7">
            @if (!empty($data['tag']))
                <span class="md-tag md-tag-accent mb-3 d-inline-block">{{ $data['tag'] }}</span>
            @endif
            <h1>{{ $data['heading'] }}</h1>
            @if (!empty($data['body']))
                <p class="lead">{{ $data['body'] }}</p>
            @endif
            @if (!empty($data['search_placeholder']))
                <form class="d-flex mt-4" style="max-width: 480px;" action="{{ route('catalog.search') }}" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="{{ $data['search_placeholder'] }}">
                    <button class="md-btn md-btn-primary" type="submit">Search</button>
                </form>
            @endif
            <div class="d-flex gap-3 mt-4">
                @if (!empty($data['cta_primary_label']) && !empty($data['cta_primary_url']))
                    <a href="{{ $data['cta_primary_url'] }}" class="md-btn md-btn-primary">{{ $data['cta_primary_label'] }}</a>
                @endif
                @if (!empty($data['cta_secondary_label']) && !empty($data['cta_secondary_url']))
                    <a href="{{ $data['cta_secondary_url'] }}" class="md-btn md-btn-secondary">{{ $data['cta_secondary_label'] }}</a>
                @endif
            </div>
        </div>
        @if (!empty($data['image']))
            <div class="col-md-5">
                <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid md-grayscale" alt="{{ $data['heading'] ?? '' }}">
            </div>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Register the block in `PageResource.php`**

Add inside the `->blocks([...])` array (after the existing `Block::make('hero_carousel')` entry, around line 86):

```php
                    Block::make('hero_banner')
                        ->label('Hero Banner (Modernist)')
                        ->schema([
                            TextInput::make('tag'),
                            TextInput::make('heading')->required(),
                            Textarea::make('body'),
                            TextInput::make('search_placeholder')
                                ->default('Search for item by keyword or product number'),
                            TextInput::make('cta_primary_label')->default('Browse Products'),
                            TextInput::make('cta_primary_url')->default('/products'),
                            TextInput::make('cta_secondary_label')->default('Request a Quote'),
                            TextInput::make('cta_secondary_url')->default('/#rfq'),
                            FileUpload::make('image')
                                ->image()
                                ->directory('page-blocks'),
                        ]),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=HeroBannerBlockTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/blocks/hero_banner.blade.php app/Filament/Resources/PageResource.php tests/Feature/HeroBannerBlockTest.php
git commit -m "feat: add hero_banner Page block"
```

---

## Task 4: `trust_badges` block (new)

**Files:**
- Create: `resources/views/blocks/trust_badges.blade.php`
- Modify: `app/Filament/Resources/PageResource.php` (register the block)
- Test: `tests/Feature/TrustBadgesBlockTest.php`

**Interfaces:**
- Produces: block type `trust_badges` with data shape `{items: [{icon,
  label}]}`, `icon` constrained to one of `shield-check`, `package-check`,
  `handshake`, `message-square`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustBadgesBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_label_and_icon_for_each_badge(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'shield-check', 'label' => 'Verified Suppliers'],
                    ['icon' => 'package-check', 'label' => 'Quality Inspected'],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Verified Suppliers');
        $response->assertSee('Quality Inspected');
        $response->assertSee('<svg', escape: false);
    }

    public function test_a_badge_with_an_unrecognized_icon_still_renders_its_label(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'not-a-real-icon', 'label' => 'Direct Supplier Contact'],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Direct Supplier Contact');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TrustBadgesBlockTest`
Expected: FAIL — `blocks.trust_badges` view doesn't exist.

- [ ] **Step 3: Create the block view**

```blade
{{-- resources/views/blocks/trust_badges.blade.php --}}
@php
    $icons = [
        'shield-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>',
        'package-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 16 2 2 4-4"/><path d="M21 12.5V6.5a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 6.5v9a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l1.5-.86"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>',
        'handshake' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 4h8"/></svg>',
        'message-square' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    ];
@endphp
<div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
    @foreach ($data['items'] ?? [] as $badge)
        <div class="col d-flex align-items-start gap-3">
            @if (isset($icons[$badge['icon'] ?? '']))
                <span class="text-danger flex-shrink-0">{!! $icons[$badge['icon']] !!}</span>
            @endif
            <span class="fw-bold">{{ $badge['label'] ?? '' }}</span>
        </div>
    @endforeach
</div>
```

- [ ] **Step 4: Register the block in `PageResource.php`**

Add inside the `->blocks([...])` array:

```php
                    Block::make('trust_badges')
                        ->label('Trust Badges')
                        ->schema([
                            Repeater::make('items')
                                ->schema([
                                    Select::make('icon')
                                        ->options([
                                            'shield-check' => 'Shield Check',
                                            'package-check' => 'Package Check',
                                            'handshake' => 'Handshake',
                                            'message-square' => 'Message Square',
                                        ])
                                        ->required(),
                                    TextInput::make('label')->required(),
                                ])
                                ->minItems(1),
                        ]),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TrustBadgesBlockTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/blocks/trust_badges.blade.php app/Filament/Resources/PageResource.php tests/Feature/TrustBadgesBlockTest.php
git commit -m "feat: add trust_badges Page block"
```

---

## Task 5: `featured_categories` — restyle + live product count + view-all link

**Files:**
- Modify: `resources/views/blocks/featured_categories.blade.php`
- Test: `tests/Feature/FeaturedCategoriesProductCountTest.php`

**Interfaces:**
- Consumes: `App\Support\CategoryHierarchy::descendantAndSelfIds(Category):
  array` (existing).
- No schema change — `category_ids`/`heading` stay as-is.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedCategoriesProductCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_leaf_category_shows_its_own_published_product_count(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'name' => 'Aerial Cable']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'draft']);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['category_ids' => [$category->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('2 products');
    }

    public function test_a_hub_category_sums_published_products_across_its_descendants(): void
    {
        $hub = Category::factory()->create(['status' => 'published', 'name' => 'Fiber Optic Cable']);
        $child = Category::factory()->create(['parent_id' => $hub->id, 'status' => 'published']);
        Product::factory()->create(['category_id' => $child->id, 'status' => 'published']);
        Product::factory()->create(['category_id' => $child->id, 'status' => 'published']);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['category_ids' => [$hub->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('2 products');
    }

    public function test_it_links_to_the_full_product_catalog(): void
    {
        $category = Category::factory()->create(['status' => 'published']);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['category_ids' => [$category->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('View all categories');
        $response->assertSee('href="'.url('/products').'"', escape: false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FeaturedCategoriesProductCountTest`
Expected: FAIL — no product count or "View all categories" link rendered yet.

- [ ] **Step 3: Update the block view**

```blade
{{-- resources/views/blocks/featured_categories.blade.php --}}
@php
    $categoryOrder = array_flip($data['category_ids'] ?? []);
    $categories = \App\Models\Category::query()
        ->whereIn('id', $data['category_ids'] ?? [])
        ->where('status', 'published')
        ->get()
        ->sortBy(fn ($category) => $categoryOrder[$category->id] ?? PHP_INT_MAX)
        ->values();
@endphp
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        @if (!empty($data['heading']))
            <h2 class="mb-0">{{ $data['heading'] }}</h2>
        @endif
        <a href="{{ url('/products') }}" class="md-btn md-btn-ghost">View all categories</a>
    </div>
    <div class="row row-cols-1 row-cols-md-4 g-4">
        @foreach ($categories as $category)
            @php
                $productCount = \App\Models\Product::query()
                    ->whereIn('category_id', \App\Support\CategoryHierarchy::descendantAndSelfIds($category))
                    ->where('status', 'published')
                    ->count();
            @endphp
            <div class="col">
                <a href="{{ url('/products/'.$category->path()) }}" class="md-card h-100 text-decoration-none d-block">
                    @if ($category->image)
                        <img src="{{ asset('storage/'.$category->image) }}" class="w-100 md-grayscale" alt="{{ $category->name }}">
                    @endif
                    <div class="p-3">
                        <h5 class="mb-1" style="color: var(--color-text);">{{ $category->name }}</h5>
                        <span class="text-muted small">{{ $productCount }} products</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FeaturedCategoriesProductCountTest`
Expected: PASS

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: PASS — `test_a_featured_categories_block_links_to_the_full_nested_path`
and `test_featured_categories_render_in_the_order_the_editor_chose_them` still
hold, since the ordering/linking logic is unchanged.

- [ ] **Step 5: Commit**

```bash
git add resources/views/blocks/featured_categories.blade.php tests/Feature/FeaturedCategoriesProductCountTest.php
git commit -m "feat: restyle featured_categories block with live product counts"
```

---

## Task 6: `deals_banner` block (new)

**Files:**
- Create: `resources/views/blocks/deals_banner.blade.php`
- Modify: `app/Filament/Resources/PageResource.php` (register the block)
- Test: `tests/Feature/DealsBannerBlockTest.php`

**Interfaces:**
- Produces: block type `deals_banner` with data shape `{heading, body,
  cta_label, cta_url}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsBannerBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_heading_body_and_cta(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'deals_banner', 'data' => [
                    'heading' => 'Bulk Deals This Week',
                    'body' => 'Save on high-volume orders across select categories.',
                    'cta_label' => 'Shop Deals',
                    'cta_url' => '/products',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Bulk Deals This Week');
        $response->assertSee('Save on high-volume orders across select categories.');
        $response->assertSee('Shop Deals');
        $response->assertSee('href="/products"', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DealsBannerBlockTest`
Expected: FAIL — `blocks.deals_banner` view doesn't exist.

- [ ] **Step 3: Create the block view**

```blade
{{-- resources/views/blocks/deals_banner.blade.php --}}
<div class="md-card mb-4 p-5 text-center" style="background: var(--color-accent); color: #fff; border-color: var(--color-accent);">
    @if (!empty($data['heading']))
        <h2 style="color: #fff;">{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['body']))
        <p class="mb-4">{{ $data['body'] }}</p>
    @endif
    @if (!empty($data['cta_label']) && !empty($data['cta_url']))
        <a href="{{ $data['cta_url'] }}" class="md-btn md-btn-secondary" style="color: #fff; border-color: #fff;">{{ $data['cta_label'] }}</a>
    @endif
</div>
```

- [ ] **Step 4: Register the block in `PageResource.php`**

Add inside the `->blocks([...])` array:

```php
                    Block::make('deals_banner')
                        ->label('Deals Banner')
                        ->schema([
                            TextInput::make('heading')->default('Bulk Deals This Week'),
                            Textarea::make('body'),
                            TextInput::make('cta_label')->default('Shop Deals'),
                            TextInput::make('cta_url')->default('/products'),
                        ]),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=DealsBannerBlockTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/blocks/deals_banner.blade.php app/Filament/Resources/PageResource.php tests/Feature/DealsBannerBlockTest.php
git commit -m "feat: add deals_banner Page block"
```

---

## Task 7: `featured_products` — restyle + price/MOQ + view-all link

**Files:**
- Modify: `resources/views/blocks/featured_products.blade.php`
- Test: `tests/Feature/FeaturedProductsCardDetailsTest.php`

**Interfaces:**
- No schema change — `product_ids`/`heading` stay as-is.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedProductsCardDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_shows_price_and_moq(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'name' => 'Cat6 Ethernet Cable',
            'price_display' => '₹45/meter',
            'quantity' => 500,
        ]);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$product->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('₹45/meter', escape: false);
        $response->assertSee('MOQ: 500');
    }

    public function test_it_links_to_the_full_product_catalog(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$product->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('View all products');
        $response->assertSee('href="'.url('/products').'"', escape: false);
    }

    public function test_no_supplier_or_seller_name_is_ever_rendered(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$product->id]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee($product->seller->company_name ?? 'no-seller-name-set');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FeaturedProductsCardDetailsTest`
Expected: FAIL — price/MOQ/view-all link not rendered yet.

- [ ] **Step 3: Update the block view**

```blade
{{-- resources/views/blocks/featured_products.blade.php --}}
@php
    $productOrder = array_flip($data['product_ids'] ?? []);
    $products = \App\Models\Product::with('images')
        ->whereIn('id', $data['product_ids'] ?? [])
        ->where('status', 'published')
        ->get()
        ->sortBy(fn ($product) => $productOrder[$product->id] ?? PHP_INT_MAX)
        ->values();
@endphp
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        @if (!empty($data['heading']))
            <h2 class="mb-0">{{ $data['heading'] }}</h2>
        @endif
        <a href="{{ url('/products') }}" class="md-btn md-btn-ghost">View all products</a>
    </div>
    <div class="row row-cols-1 row-cols-md-4 g-4">
        @foreach ($products as $product)
            <div class="col">
                <a href="{{ url('/products/'.$product->path()) }}" class="md-card h-100 text-decoration-none d-block">
                    <div class="md-grayscale">
                        <x-product-thumbnail :path="optional($product->primaryImage())->path" :alt="$product->name" />
                    </div>
                    <div class="p-3">
                        <h5 class="mb-1" style="color: var(--color-text);">{{ $product->name }}</h5>
                        @if ($product->quantity)
                            <div class="small text-muted">MOQ: {{ $product->quantity }}</div>
                        @endif
                        @if ($product->price_display)
                            <div class="fw-bold" style="color: var(--color-accent-700);">{{ $product->price_display }}</div>
                        @endif
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FeaturedProductsCardDetailsTest`
Expected: PASS

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: PASS — `test_a_featured_products_block_only_shows_published_products`
and `test_a_featured_products_block_renders_a_fixed_size_thumbnail` still
hold (the `<x-product-thumbnail>` component and its `width="132"` output are
untouched; it's just wrapped in a new `<div class="md-grayscale">`).

- [ ] **Step 5: Commit**

```bash
git add resources/views/blocks/featured_products.blade.php tests/Feature/FeaturedProductsCardDetailsTest.php
git commit -m "feat: restyle featured_products block with price, MOQ, and view-all link"
```

---

## Task 8: `rfq_form_embed` — restyle + optional tag/body copy

**Files:**
- Modify: `resources/views/blocks/rfq_form_embed.blade.php`
- Modify: `app/Filament/Resources/PageResource.php` (extend the block's schema)
- Modify: `public/css/modernist.css` (append scoped form-override rules)
- Test: `tests/Feature/RfqFormEmbedTagBodyTest.php`

**Interfaces:**
- Extends the existing `rfq_form_embed` block's data shape: adds optional
  `tag` and `body`. `heading` keeps its existing default (`'Request a
  Quote'`) and behavior.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqFormEmbedTagBodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_optional_tag_and_body(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => [
                    'heading' => "Can't find exactly what you need?",
                    'tag' => 'Request for Quote',
                    'body' => "Tell us what you're looking for and our sourcing team will get back to you.",
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Request for Quote');
        $response->assertSee("Tell us what you're looking for");
    }

    public function test_tag_and_body_are_optional_and_the_form_still_renders_without_them(): void
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
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RfqFormEmbedTagBodyTest`
Expected: FAIL on the first test (`tag`/`body` not rendered); the second test
already passes against the current view (confirms backward compatibility
before the change).

- [ ] **Step 3: Update the block view**

```blade
{{-- resources/views/blocks/rfq_form_embed.blade.php --}}
<div class="md-rfq-card md-card p-4 p-md-5 mb-4">
    @if (!empty($data['tag']))
        <span class="md-tag md-tag-accent mb-3 d-inline-block">{{ $data['tag'] }}</span>
    @endif
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['body']))
        <p class="text-muted">{{ $data['body'] }}</p>
    @endif
    @include('partials.quote-request-form-fields', ['product' => null, 'modal' => false, 'idSuffix' => '-embed-'.($blockKey ?? 0)])
</div>
```

- [ ] **Step 4: Extend the block schema in `PageResource.php`**

Change the existing `Block::make('rfq_form_embed')` entry to:

```php
                    Block::make('rfq_form_embed')
                        ->label('RFQ Form Embed')
                        ->schema([
                            TextInput::make('tag'),
                            TextInput::make('heading')->default('Request a Quote'),
                            Textarea::make('body'),
                        ]),
```

- [ ] **Step 5: Append scoped form-restyle CSS**

Append to `public/css/modernist.css`:

```css
/* RFQ form embed: scoped override of the shared quote-request form's
   Bootstrap classes. Scoped to .md-rfq-card so the same partial keeps its
   default Bootstrap look everywhere else it's used (e.g. the product-detail
   modal). */
.md-rfq-card .form-label {
    font-family: var(--font-heading);
    font-weight: 600;
    color: var(--color-text);
}

.md-rfq-card .form-control,
.md-rfq-card .form-select {
    border: 2px solid var(--color-divider);
    border-radius: var(--radius-0);
}

.md-rfq-card .form-control:focus,
.md-rfq-card .form-select:focus {
    border-color: var(--color-accent);
    box-shadow: none;
}

.md-rfq-card .btn-primary {
    background-color: var(--color-accent);
    border-color: var(--color-accent);
    border-radius: var(--radius-0);
    font-family: var(--font-heading);
    font-weight: 600;
}

.md-rfq-card .btn-primary:hover,
.md-rfq-card .btn-primary:focus,
.md-rfq-card .btn-primary:active {
    background-color: var(--color-accent-700);
    border-color: var(--color-accent-700);
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=RfqFormEmbedTagBodyTest`
Expected: PASS

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: PASS — `test_an_rfq_form_embed_block_renders_the_form_inline_without_a_modal`
and `test_two_rfq_form_embed_blocks_on_one_page_do_not_produce_duplicate_ids`
still hold, since the `@include` call and its `$idSuffix` are unchanged.

- [ ] **Step 7: Commit**

```bash
git add resources/views/blocks/rfq_form_embed.blade.php app/Filament/Resources/PageResource.php public/css/modernist.css tests/Feature/RfqFormEmbedTagBodyTest.php
git commit -m "feat: restyle rfq_form_embed block and add optional tag/body copy"
```

---

## Task 9: Newsletter signup (new `subscribers` table, route, block)

**Files:**
- Create: `database/migrations/2026_08_04_100000_create_subscribers_table.php`
- Create: `app/Models/Subscriber.php`
- Create: `app/Http/Controllers/NewsletterController.php`
- Create: `resources/views/blocks/newsletter_signup.blade.php`
- Modify: `app/Filament/Resources/PageResource.php` (register the block)
- Modify: `routes/web.php` (new route)
- Modify: `resources/views/layouts/app.blade.php` (flash message)
- Test: `tests/Feature/NewsletterSubscriptionTest.php`,
  `tests/Feature/NewsletterSignupBlockTest.php`

**Interfaces:**
- Produces: `Subscriber` model (`fillable = ['email']`), route
  `newsletter.subscribe` (`POST /newsletter/subscribe`), flash key
  `newsletter_subscribed`.

- [ ] **Step 1: Write the failing subscription tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_email_creates_a_subscriber_and_shows_a_flash_message(): void
    {
        $response = $this->post('/newsletter/subscribe', ['email' => 'buyer@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('newsletter_subscribed');
        $this->assertDatabaseHas('subscribers', ['email' => 'buyer@example.com']);
    }

    public function test_resubmitting_the_same_email_does_not_create_a_duplicate_row(): void
    {
        Subscriber::query()->create(['email' => 'buyer@example.com']);

        $response = $this->post('/newsletter/subscribe', ['email' => 'buyer@example.com']);

        $response->assertRedirect();
        $this->assertDatabaseCount('subscribers', 1);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $response = $this->post('/newsletter/subscribe', ['email' => 'not-an-email']);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('subscribers', 0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=NewsletterSubscriptionTest`
Expected: FAIL — no `subscribers` table, model, controller, or route yet.

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
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email'];
}
```

- [ ] **Step 5: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        Subscriber::query()->firstOrCreate(['email' => $validated['email']]);

        return back()->with('newsletter_subscribed', true);
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add the import and the route next to the RFQ route:

```php
use App\Http\Controllers\NewsletterController;
```

```php
Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])->name('newsletter.subscribe');
```

- [ ] **Step 7: Run test database migrations and verify the subscription tests pass**

Run: `php artisan test --filter=NewsletterSubscriptionTest`
Expected: PASS

- [ ] **Step 8: Commit the backend**

```bash
git add database/migrations/2026_08_04_100000_create_subscribers_table.php app/Models/Subscriber.php app/Http/Controllers/NewsletterController.php routes/web.php tests/Feature/NewsletterSubscriptionTest.php
git commit -m "feat: add newsletter subscription backend"
```

- [ ] **Step 9: Write the failing block test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSignupBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_heading_subheading_and_posts_to_the_subscribe_route(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'newsletter_signup', 'data' => [
                    'heading' => 'Get sourcing updates & deals',
                    'subheading' => 'One email a month, no spam.',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Get sourcing updates & deals');
        $response->assertSee('One email a month, no spam.');
        $response->assertSee('action="'.route('newsletter.subscribe').'"', escape: false);
    }
}
```

- [ ] **Step 10: Run test to verify it fails**

Run: `php artisan test --filter=NewsletterSignupBlockTest`
Expected: FAIL — `blocks.newsletter_signup` view doesn't exist.

- [ ] **Step 11: Create the block view**

```blade
{{-- resources/views/blocks/newsletter_signup.blade.php --}}
<div class="md-card p-4 p-md-5 mb-4 text-center">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['subheading']))
        <p class="text-muted">{{ $data['subheading'] }}</p>
    @endif
    <form class="d-flex justify-content-center gap-2 mt-3" style="max-width: 480px; margin: 0 auto;" action="{{ route('newsletter.subscribe') }}" method="POST">
        @csrf
        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
        <button type="submit" class="md-btn md-btn-primary">Subscribe</button>
    </form>
</div>
```

- [ ] **Step 12: Register the block in `PageResource.php`**

Add inside the `->blocks([...])` array:

```php
                    Block::make('newsletter_signup')
                        ->label('Newsletter Signup')
                        ->schema([
                            TextInput::make('heading')->default('Get sourcing updates & deals'),
                            TextInput::make('subheading'),
                        ]),
```

- [ ] **Step 13: Add the flash message to the layout**

In `resources/views/layouts/app.blade.php`, next to the existing
`quote_request_submitted` flash alert inside `<main>`, add:

```blade
        @if (session('newsletter_subscribed'))
            <div class="alert alert-success">Thanks for subscribing — you're on the list.</div>
        @endif
```

- [ ] **Step 14: Run tests to verify they pass**

Run: `php artisan test --filter=NewsletterSignupBlockTest`
Expected: PASS

- [ ] **Step 15: Commit**

```bash
git add resources/views/blocks/newsletter_signup.blade.php app/Filament/Resources/PageResource.php resources/views/layouts/app.blade.php tests/Feature/NewsletterSignupBlockTest.php
git commit -m "feat: add newsletter_signup Page block"
```

---

## Task 10: Assemble the new Homepage content

**Files:**
- Modify: `database/seeders/PageSeeder.php` (home page `content` array)
- Test: `tests/Feature/HomepageSeedContentTest.php`

**Interfaces:**
- Consumes every block type produced by Tasks 3-9, plus the existing
  `featured_categories`/`featured_products` (Tasks 5/7) and `rfq_form_embed`
  (Task 8).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSeedContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_home_page_renders_every_new_block_without_error(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sourcing Cable & Wire'); // assertSee escapes this for us — matches Blade's auto-escaped output
        $response->assertSee('Verified Suppliers');
        $response->assertSee('Bulk Deals This Week');
        $response->assertSee('Get sourcing updates & deals');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HomepageSeedContentTest`
Expected: FAIL — the seeded home page still has the old `hero_carousel`/
`content_strip` content.

- [ ] **Step 3: Update `database/seeders/PageSeeder.php`**

Replace the `home` page's `firstOrCreate` call with:

```php
        Page::query()->firstOrCreate(['slug' => 'home'], [
            'title' => 'Home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_banner', 'data' => [
                    'tag' => 'B2B Sourcing Marketplace',
                    'heading' => 'Sourcing Cable & Wire — and Everything Else — Simplified',
                    'body' => 'Browse our catalog and request a quote — no account required.',
                    'search_placeholder' => 'Search for item by keyword or product number',
                    'cta_primary_label' => 'Browse Products',
                    'cta_primary_url' => '/products',
                    'cta_secondary_label' => 'Request a Quote',
                    'cta_secondary_url' => '/#rfq',
                ]],
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'shield-check', 'label' => 'Verified Suppliers'],
                    ['icon' => 'package-check', 'label' => 'Quality Inspected'],
                    ['icon' => 'handshake', 'label' => 'Direct Supplier Contact'],
                    ['icon' => 'message-square', 'label' => 'RFQ Support'],
                ]]],
                ['type' => 'featured_categories', 'data' => ['heading' => 'Shop by Category', 'category_ids' => []]],
                ['type' => 'deals_banner', 'data' => [
                    'heading' => 'Bulk Deals This Week',
                    'body' => 'Save on high-volume orders across select categories.',
                    'cta_label' => 'Shop Deals',
                    'cta_url' => '/products',
                ]],
                ['type' => 'featured_products', 'data' => ['heading' => 'Featured Products', 'product_ids' => []]],
                ['type' => 'rfq_form_embed', 'data' => [
                    'tag' => 'Request for Quote',
                    'heading' => "Can't find exactly what you need?",
                    'body' => "Tell us what you're looking for and our sourcing team will get back to you.",
                ]],
                ['type' => 'newsletter_signup', 'data' => [
                    'heading' => 'Get sourcing updates & deals',
                    'subheading' => 'One email a month, no spam.',
                ]],
            ],
        ]);
```

Note: `featured_categories`/`featured_products` seed with empty
`category_ids`/`product_ids` because the seeder has no way to know which real
catalog rows exist in a given environment — an Admin picks the actual
categories/products to feature via `/admin` after seeding, same as any other
curated block.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HomepageSeedContentTest`
Expected: PASS

Run: `php artisan test --filter=NavigationRenderingTest`
Expected: PASS — `test_seeded_home_and_contact_us_pages_are_reachable` still
holds (`/` and `/contact-us` both still return 200).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/PageSeeder.php tests/Feature/HomepageSeedContentTest.php
git commit -m "feat: assemble the new Homepage content in PageSeeder"
```

---

## Task 11: Apply to the dev database and review at localhost:8000

This task has no new automated tests — it's the manual verification gate the
user explicitly asked for before anything goes live. **Do not deploy or push
to production as part of this task; stop after Step 5 and wait for the user's
explicit go-ahead.**

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: every test passes, including all tests added in Tasks 1-10 and
every pre-existing test listed in "Context for the implementer" above.

- [ ] **Step 2: Apply the new migration to the dev database**

Run: `php artisan migrate`
Expected: the `2026_08_04_100000_create_subscribers_table` migration applies
cleanly; no other tables are touched (per `CLAUDE.md`, never run
`migrate:fresh` against this database).

- [ ] **Step 3: Update the existing `home` Page row's content**

The dev database already has a `home` row from before this plan (seeded with
the old `hero_carousel`/`content_strip` blocks); `PageSeeder`'s
`firstOrCreate` won't touch an existing row, so delete just that row and
re-run the seeder to recreate it with the new content:

Run:
```
php artisan tinker
>>> App\Models\Page::where('slug', 'home')->delete();
>>> (new Database\Seeders\PageSeeder())->run();
```

Every other page the seeder creates (`contact-us`, `terms-and-conditions`)
already exists, so `firstOrCreate` leaves those untouched — only the
freshly-deleted `home` row gets recreated, with the new block content.
Confirm afterward with:
```
php artisan tinker --execute="echo App\Models\Page::where('slug','home')->first()->content[0]['type'];"
```
Expected output: `hero_banner`.

- [ ] **Step 4: Pick real categories/products to feature**

Go to `/admin` → Pages → Home, open the `featured_categories` and
`featured_products` blocks, and select real category/product rows from the
dev database's catalog (they seeded empty in Task 10). Save.

- [ ] **Step 5: Manually review at `http://localhost:8000`**

Run `php artisan serve`, then visit `http://localhost:8000` and check:
- Utility bar shows "Ship to: India | English" and "Become a Seller"
  (and "Help Center" if that page exists).
- Header/nav render in the new look; the mega-menu still opens and lists the
  live category tree exactly as before.
- Hero banner, trust badges, category grid (with real product counts), deals
  banner, featured products (with price/MOQ), RFQ section, and newsletter
  section all render.
- Submitting the RFQ form on the homepage produces the same
  `quote_request_submitted` flash/quote-number confirmation as the RFQ form
  anywhere else on the site.
- Submitting the newsletter form shows the "Thanks for subscribing" flash and
  creates a row in `subscribers` (check via tinker:
  `App\Models\Subscriber::count()`).
- Footer still renders `$footerNavItems`, address/phone/email, and social
  links exactly as before, just restyled.
- No supplier/seller name appears anywhere on the page.

**Stop here.** Do not deploy to Railway/production until the user has done
this review themselves and explicitly confirms.
