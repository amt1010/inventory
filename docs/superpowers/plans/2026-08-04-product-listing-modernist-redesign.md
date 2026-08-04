# Product Listing Modernist Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the existing leaf-category product listing (`CatalogController` +
`catalog.category`/`catalog.partials.product-grid-items`) with the Modernist
design system, and add real sort + attribute-driven sidebar filtering.

**Architecture:** Entirely additive to the existing `CatalogController::show()`
and its two Blade views — no new route, no new controller. Sort and filters
are plain query-string params read directly by the controller; filter groups
are computed from the existing `custom_attributes` table (no new schema).
The already-shipped infinite-scroll mechanism (IntersectionObserver + AJAX
partial) is preserved exactly, just made query-string-aware via the
paginator's `withQueryString()`.

**Tech Stack:** Laravel 11, PHP 8.2, Blade + Bootstrap 5 (CDN, unchanged),
`public/css/modernist.css` design tokens/`md`-prefixed classes (already
built in the Homepage phase — no new tokens needed here), MySQL (dev) /
SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **Only touches the leaf-product branch of `catalog.category`.** The
  hub/tile branch (`@if ($children->isNotEmpty())`, `catalog/category.blade.php`
  lines 29-44) is not modified — categories with children keep showing child
  tiles exactly as today. `catalog.search` is not touched.
- **No new schema/migration.** Filters are computed and applied entirely
  against the existing `custom_attributes` table
  (`app/Models/CustomAttribute.php`, `label`/`value` columns) via its
  existing `MorphMany`/`MorphTo` relations — nothing here requires a new
  column or table.
- **No Price Range or "Trade Assurance" filter, no price sort.**
  `products.price_display` is free text, not numeric — out of scope per the
  spec.
- **No "Deal" tag, no supplier/seller name anywhere.** Same rules already
  enforced on every Homepage block; carried over unchanged. `products.status
  = 'published'` is the only visibility gate, same as today.
- **Infinite scroll stays infinite scroll.** Do not introduce numbered
  pagination. The existing `IntersectionObserver`/fetch script in
  `catalog/category.blade.php` (currently lines 55-105) is preserved
  byte-for-byte except for the query-string-preservation change described in
  Task 1 — do not rewrite it.
- **Correction from the design spec:** the spec's design-presentation step
  said sidebar work would happen "alongside the existing category-tree
  sidebar" — there is no such sidebar in the current codebase (only a
  breadcrumb and, for hub categories, child tiles). This plan does not
  invent one; the sidebar built here contains only the attribute filters.
- Tests run against SQLite in-memory (`phpunit.xml`); apply any future
  migration (none needed for this plan) to the dev database with `php
  artisan migrate`, never `migrate:fresh`, per `CLAUDE.md`.

## Context for the implementer

- `app/Http/Controllers/CatalogController.php` — `show(Request $request,
  string $path = '')` resolves the category-tree path segment by segment,
  then (lines 55-73): builds `$products` from `$category->products()->where
  ('status', 'published')->orderBy('sort_order')->paginate(9)`, returns the
  `catalog.partials.product-grid-items` partial for AJAX requests (`header
  ('X-Next-Page-Url', ...)` for the infinite-scroll script to read), or the
  full `catalog.category` view otherwise. `$children` is computed
  separately and always passed regardless of whether `$products` is empty.
- `resources/views/catalog/category.blade.php` (108 lines) — breadcrumb,
  optional child-tile grid, then (currently lines 46-107) the product grid:
  `@if ($products->isNotEmpty())` wraps a `#product-grid` div (includes
  `catalog.partials.product-grid-items`), a hidden `#product-grid-loader`
  spinner, a `#product-grid-sentinel` div carrying `data-next-page-url`, and
  an inline `<script>` that observes the sentinel and fetches+appends the
  next page via `fetch(nextUrl, {headers: {'X-Requested-With':
  'XMLHttpRequest'}})`, reading the next URL from the `X-Next-Page-Url`
  response header each time.
- `resources/views/catalog/partials/product-grid-items.blade.php` (14
  lines) — `@foreach ($products as $product)` renders a Bootstrap `.card`
  per product (image via `$product->images->first()`, name, short
  description), linking to `/products/{breadcrumb-slugs}/{product-slug}`.
- `app/Models/CustomAttribute.php` — `fillable = ['label', 'value',
  'file_path', 'sort_order']`, `attributable(): MorphTo`. Products already
  expose the inverse via `Product::customAttributes(): MorphMany` (already
  defined, `app/Models/Product.php`). Sellers already create these rows
  today via `CustomAttributesRelationManager` on the Seller product form —
  nothing to build there.
- `resources/views/partials/quote-request-form.blade.php` — the existing
  per-product quote modal, already used on the product detail page
  (`catalog/product.blade.php:135`). Its modal id (`quoteRequestModal-
  {id}`) and every form-field id inside it key off `$product->id` (via
  `quote-request-form-fields.blade.php`'s `$idSuffix` default), so including
  it once per product card on a listing page is already collision-safe —
  nothing in that partial needs to change.
- `resources/views/components/product-thumbnail.blade.php` — fixed 132×132
  `<img>` component, `@props(['path' => null, 'alt' => ''])`. Do not modify.
- `public/css/modernist.css` — already has `.md-card`, `.md-btn`/`.md-btn-
  primary`/`.md-btn-block`, `.md-grayscale`, `.md-tag`, and the `--color-*`/
  `--space-*` tokens from the Homepage phase. No new tokens needed; reuse
  these classes directly.
- Existing tests that **must keep passing unchanged**:
  `tests/Feature/CatalogRoutingTest.php` (in particular
  `test_a_category_page_shows_only_the_first_nine_published_products` and
  `test_a_second_page_of_products_can_be_fetched_via_ajax`, which assert
  `$products->count()` via `assertViewHas`), and
  `tests/Feature/CatalogSearchTest.php` (untouched code path, included here
  only as a regression check).

---

## Task 1: Sort (query param, controller only)

**Files:**
- Modify: `app/Http/Controllers/CatalogController.php:55-57`
- Test: `tests/Feature/CatalogSortTest.php`

**Interfaces:**
- Produces: `?sort=newest|name_asc|name_desc` query param handling on
  `/products/{path}`. Unrecognized/absent falls back to the existing
  `orderBy('sort_order')`. `$products` is now a paginator with
  `withQueryString()` applied, so `$products->nextPageUrl()` carries the
  active `sort` param forward — later tasks' query params ride the same
  mechanism.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_newest_orders_by_created_at_descending(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $older = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Older']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Newer']);

        $response = $this->get('/products/'.$category->slug.'?sort=newest');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_sort_name_asc_orders_alphabetically(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $b = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Banana Cable']);
        $a = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Apple Cable']);

        $response = $this->get('/products/'.$category->slug.'?sort=name_asc');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$a->id, $b->id], $ids);
    }

    public function test_no_sort_param_falls_back_to_the_existing_sort_order_field(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $second = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'sort_order' => 2]);
        $first = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'sort_order' => 1]);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], $ids);
    }

    public function test_the_next_page_url_preserves_the_active_sort_param(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->count(12)->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug.'?sort=newest');

        $response->assertOk();
        $nextPageUrl = $response->viewData('products')->nextPageUrl();
        $this->assertStringContainsString('sort=newest', $nextPageUrl);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CatalogSortTest`
Expected: FAIL — `sort` param is currently ignored, and `nextPageUrl()`
carries no query string.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/CatalogController.php`, replace lines 55-57:

```php
        $products = $category
            ? $category->products()->where('status', 'published')->orderBy('sort_order')->paginate(9)
            : collect();
```

with:

```php
        $products = collect();

        if ($category) {
            $productsQuery = $category->products()->with('images')->where('status', 'published');

            $productsQuery = match ($request->query('sort')) {
                'newest' => $productsQuery->orderBy('created_at', 'desc'),
                'name_asc' => $productsQuery->orderBy('name'),
                'name_desc' => $productsQuery->orderBy('name', 'desc'),
                default => $productsQuery->orderBy('sort_order'),
            };

            $products = $productsQuery->paginate(9)->withQueryString();
        }
```

(`->with('images')` is added here too — the product cards need it and the
query didn't eager-load it before, which was an N+1 on every card's image.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CatalogSortTest`
Expected: PASS

Run: `php artisan test --filter=CatalogRoutingTest`
Expected: PASS — the 9-per-page and AJAX-second-page tests still hold; only
the ordering mechanism changed, not the pagination size or AJAX branch.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CatalogController.php tests/Feature/CatalogSortTest.php
git commit -m "feat: add sort query param to catalog product listing"
```

---

## Task 2: Attribute-driven filters (controller only)

**Files:**
- Modify: `app/Http/Controllers/CatalogController.php`
- Test: `tests/Feature/CatalogAttributeFilterTest.php`

**Interfaces:**
- Consumes: `App\Models\CustomAttribute` (existing), `Product::
  customAttributes()` (existing `MorphMany`).
- Produces: `$filterGroups` (a `Collection<string, Collection<int, string>>`
  keyed by attribute label, each value a sorted, de-duplicated list of that
  label's distinct values among the category's published products) passed
  to the `catalog.category` view. `?attr[Label][]=Value` query param
  handling — values within one label are OR'd, different labels are AND'd.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAttributeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_controller_computes_one_filter_group_per_distinct_attribute_label(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertViewHas('filterGroups', function ($groups) {
            return $groups->has('Color') && $groups['Color']->sort()->values()->all() === ['Blue', 'Red'];
        });
    }

    public function test_a_category_with_no_custom_attributes_has_no_filter_groups(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertViewHas('filterGroups', fn ($groups) => $groups->isEmpty());
    }

    public function test_selecting_a_value_narrows_results_to_matching_products_only(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$red->id], $ids);
    }

    public function test_selecting_two_values_in_the_same_group_matches_either(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);
        $green = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $green->customAttributes()->create(['label' => 'Color', 'value' => 'Green']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red&attr[Color][]=Blue');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$red->id, $blue->id], $ids);
    }

    public function test_selecting_values_in_two_different_groups_requires_a_product_to_match_both(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $matches = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $matches->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $matches->customAttributes()->create(['label' => 'Material', 'value' => 'Copper']);

        $colorOnly = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $colorOnly->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $colorOnly->customAttributes()->create(['label' => 'Material', 'value' => 'Aluminum']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red&attr[Material][]=Copper');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$matches->id], $ids);
    }

    public function test_the_next_page_url_preserves_active_filter_params(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $products = Product::factory()->count(12)->create(['category_id' => $category->id, 'status' => 'published']);
        $products->each(fn ($p) => $p->customAttributes()->create(['label' => 'Color', 'value' => 'Red']));

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $nextPageUrl = urldecode($response->viewData('products')->nextPageUrl());
        $this->assertStringContainsString('attr', $nextPageUrl);
        $this->assertStringContainsString('Red', $nextPageUrl);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CatalogAttributeFilterTest`
Expected: FAIL — no `filterGroups` view variable exists yet, and `attr[]`
params are ignored.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/CatalogController.php`, add the import:

```php
use App\Models\CustomAttribute;
```

Replace the block added in Task 1 with:

```php
        $products = collect();
        $filterGroups = collect();

        if ($category) {
            $filterGroups = CustomAttribute::query()
                ->whereHasMorph('attributable', [Product::class], function ($query) use ($category) {
                    $query->where('category_id', $category->id)->where('status', 'published');
                })
                ->get(['label', 'value'])
                ->groupBy('label')
                ->map(fn ($group) => $group->pluck('value')->unique()->sort()->values());

            $productsQuery = $category->products()->with('images')->where('status', 'published');

            foreach ((array) $request->query('attr', []) as $label => $values) {
                $productsQuery->whereHas('customAttributes', function ($query) use ($label, $values) {
                    $query->where('label', $label)->whereIn('value', (array) $values);
                });
            }

            $productsQuery = match ($request->query('sort')) {
                'newest' => $productsQuery->orderBy('created_at', 'desc'),
                'name_asc' => $productsQuery->orderBy('name'),
                'name_desc' => $productsQuery->orderBy('name', 'desc'),
                default => $productsQuery->orderBy('sort_order'),
            };

            $products = $productsQuery->paginate(9)->withQueryString();
        }
```

Then update the `catalog.category` view-return call (currently near the end
of the method) to pass `filterGroups` alongside the existing keys:

```php
        return view('catalog.category', [
            'category' => $category,
            'breadcrumb' => $breadcrumb,
            'children' => $category
                ? $category->children()->where('status', 'published')->get()
                : Category::query()->whereNull('parent_id')->where('status', 'published')->orderBy('sort_order')->get(),
            'products' => $products,
            'filterGroups' => $filterGroups,
        ]);
```

Do not add `filterGroups` to the AJAX partial response — the sidebar isn't
part of that partial.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CatalogAttributeFilterTest`
Expected: PASS

Run: `php artisan test --filter=CatalogSortTest`
Run: `php artisan test --filter=CatalogRoutingTest`
Expected: both PASS unchanged.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CatalogController.php tests/Feature/CatalogAttributeFilterTest.php
git commit -m "feat: add custom-attribute-driven filtering to catalog product listing"
```

---

## Task 3: Sidebar + results header UI (view layer)

**Files:**
- Modify: `resources/views/catalog/category.blade.php:46-107`
- Test: `tests/Feature/CatalogListingLayoutTest.php`

**Interfaces:**
- Consumes: `$filterGroups` and query-string-aware `$products` from Task 2.
  Introduces a single `<form id="catalog-filter-form" method="GET">` (left
  empty in the DOM) that every sort/filter control references via the HTML
  `form="catalog-filter-form"` attribute, regardless of where in the page
  they're physically positioned — this is what lets the sort dropdown (in
  the results header) and filter checkboxes (in the sidebar) submit as one
  combined query string without needing to be nested inside a single form
  element.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogListingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_total_product_count_and_a_sort_dropdown(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->count(3)->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('3 products found');
        $response->assertSee('name="sort"', escape: false);
    }

    public function test_it_renders_a_checkbox_for_each_filter_value(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('Color');
        $response->assertSee('Red');
        $response->assertSee('name="attr[Color][]"', escape: false);
    }

    public function test_a_checked_filter_checkbox_reflects_the_active_query_param(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $response->assertSee('checked', escape: false);
    }

    public function test_a_category_with_no_filter_groups_shows_no_empty_filter_panel(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertDontSee('<h6 class="mb-3">Filters</h6>', false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CatalogListingLayoutTest`
Expected: FAIL — no sidebar, sort dropdown, or results count rendered yet.

- [ ] **Step 3: Replace the products section of the view**

In `resources/views/catalog/category.blade.php`, replace lines 46-107 (the
entire `@if ($products->isNotEmpty()) ... @endif` block) with:

```blade
    @if ($products->isNotEmpty() || $filterGroups->isNotEmpty())
        <form id="catalog-filter-form" method="GET"></form>

        <div class="row">
            <div class="col-md-3 mb-4">
                @if ($filterGroups->isNotEmpty())
                    <div class="md-card p-3">
                        <h6 class="mb-3">Filters</h6>
                        @php $selectedAttrs = (array) request('attr', []); @endphp
                        @foreach ($filterGroups as $label => $values)
                            @php $selectedValues = (array) ($selectedAttrs[$label] ?? []); @endphp
                            <div class="mb-3">
                                <div class="fw-bold small text-uppercase mb-2">{{ $label }}</div>
                                @foreach ($values as $value)
                                    @php $optionId = 'attr-'.\Illuminate\Support\Str::slug($label).'-'.\Illuminate\Support\Str::slug($value); @endphp
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            form="catalog-filter-form"
                                            name="attr[{{ $label }}][]"
                                            value="{{ $value }}"
                                            id="{{ $optionId }}"
                                            onchange="document.getElementById('catalog-filter-form').submit()"
                                            @checked(in_array($value, $selectedValues))
                                        >
                                        <label class="form-check-label" for="{{ $optionId }}">{{ $value }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-md-9">
                @if ($products->isNotEmpty())
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">{{ $products->total() }} products found</span>
                        <div>
                            <label for="sort" class="small text-muted me-2">Sort by</label>
                            <select
                                name="sort"
                                id="sort"
                                form="catalog-filter-form"
                                class="form-select d-inline-block w-auto"
                                onchange="document.getElementById('catalog-filter-form').submit()"
                            >
                                <option value="">Featured</option>
                                <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name (A-Z)</option>
                                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name (Z-A)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row row-cols-1 row-cols-md-3 g-4" id="product-grid">
                        @include('catalog.partials.product-grid-items', ['products' => $products, 'breadcrumb' => $breadcrumb])
                    </div>
                    <div class="text-center py-4" id="product-grid-loader" style="display: none;">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                    </div>
                    <div id="product-grid-sentinel" data-next-page-url="{{ $products instanceof \Illuminate\Contracts\Pagination\Paginator ? $products->nextPageUrl() : '' }}"></div>

                    <script>
                        (function () {
                            var sentinel = document.getElementById('product-grid-sentinel');
                            var grid = document.getElementById('product-grid');
                            var loader = document.getElementById('product-grid-loader');
                            var loading = false;

                            var observer = new IntersectionObserver(function (entries) {
                                entries.forEach(function (entry) {
                                    if (entry.isIntersecting) {
                                        loadNextPage();
                                    }
                                });
                            });

                            function loadNextPage() {
                                var nextUrl = sentinel.getAttribute('data-next-page-url');
                                if (!nextUrl || loading) {
                                    return;
                                }

                                loading = true;
                                loader.style.display = 'block';

                                fetch(nextUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                    .then(function (response) {
                                        var nextPageUrl = response.headers.get('X-Next-Page-Url') || '';
                                        return response.text().then(function (html) {
                                            return { html: html, nextPageUrl: nextPageUrl };
                                        });
                                    })
                                    .then(function (result) {
                                        grid.insertAdjacentHTML('beforeend', result.html);
                                        sentinel.setAttribute('data-next-page-url', result.nextPageUrl);
                                        loader.style.display = 'none';
                                        loading = false;

                                        if (!result.nextPageUrl) {
                                            observer.disconnect();
                                        }
                                    })
                                    .catch(function () {
                                        loader.style.display = 'none';
                                        loading = false;
                                    });
                            }

                            if (sentinel.getAttribute('data-next-page-url')) {
                                observer.observe(sentinel);
                            }
                        })();
                    </script>
                @endif
            </div>
        </div>
    @endif
@endsection
```

(The `<script>` block is reproduced unchanged from the original — same
IntersectionObserver/fetch logic, just relocated inside the new `.col-md-9`
wrapper.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CatalogListingLayoutTest`
Expected: PASS

Run: `php artisan test --filter=CatalogRoutingTest`
Run: `php artisan test --filter=CatalogSortTest`
Run: `php artisan test --filter=CatalogAttributeFilterTest`
Expected: all PASS unchanged — none of these assert on layout markup, only
on `viewData`/status codes, so the restructure doesn't affect them.

- [ ] **Step 5: Commit**

```bash
git add resources/views/catalog/category.blade.php tests/Feature/CatalogListingLayoutTest.php
git commit -m "feat: add filter sidebar and sort/results-count header to catalog listing"
```

---

## Task 4: Product card restyle + Add to RFQ modal

**Files:**
- Modify: `resources/views/catalog/partials/product-grid-items.blade.php`
- Test: `tests/Feature/CatalogProductCardTest.php`

**Interfaces:**
- Consumes: `partials/quote-request-form.blade.php` (existing, unmodified).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_shows_price_and_moq(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'price_display' => '₹45/meter',
            'quantity' => 500,
        ]);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('₹45/meter', escape: false);
        $response->assertSee('MOQ: 500');
    }

    public function test_a_card_has_an_add_to_rfq_button_that_opens_the_products_own_modal(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('Add to RFQ');
        $response->assertSee('data-bs-target="#quoteRequestModal-'.$product->id.'"', escape: false);
        $response->assertSee('id="quoteRequestModal-'.$product->id.'"', escape: false);
    }

    public function test_two_cards_on_one_page_do_not_produce_duplicate_modal_ids(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $first = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $second = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);
        $html = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($html, 'id="quoteRequestModal-'.$first->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="quoteRequestModal-'.$second->id.'"'));
    }

    public function test_no_supplier_or_seller_name_is_ever_rendered(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertDontSee($product->seller->company_name);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CatalogProductCardTest`
Expected: FAIL on price/MOQ/Add-to-RFQ assertions; the no-supplier test
trivially passes already (no supplier line has ever existed here either).

- [ ] **Step 3: Update the card partial**

Replace `resources/views/catalog/partials/product-grid-items.blade.php`
entirely:

```blade
{{-- resources/views/catalog/partials/product-grid-items.blade.php --}}
@foreach ($products as $product)
    <div class="col">
        <div class="md-card h-100 d-flex flex-column">
            <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="text-decoration-none d-block">
                <div class="md-grayscale">
                    <x-product-thumbnail :path="optional($product->primaryImage())->path" :alt="$product->name" />
                </div>
                <div class="p-3 pb-0">
                    <h5 class="mb-1" style="color: var(--color-text);">{{ $product->name }}</h5>
                    @if ($product->quantity)
                        <div class="small text-muted">MOQ: {{ $product->quantity }}</div>
                    @endif
                    @if ($product->price_display)
                        <div class="fw-bold" style="color: var(--color-accent-700);">{{ $product->price_display }}</div>
                    @endif
                </div>
            </a>
            <div class="p-3 pt-2 mt-auto">
                <button type="button" class="md-btn md-btn-primary md-btn-block" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Add to RFQ</button>
            </div>
        </div>
    </div>
    @include('partials.quote-request-form', ['product' => $product])
@endforeach
```

(Switched from `$product->images->first()` to `$product->primaryImage()` —
matches the homepage's `featured_products` card and correctly prefers the
seller's chosen primary image instead of just whichever image sorts first.
The modal include moved outside the `.col` div so it doesn't get squeezed
into the grid's column layout — Bootstrap modals are positioned via
`position: fixed` and hidden by default, so their DOM location within the
page doesn't affect layout either way, but keeping it as a grid sibling
rather than a grid child avoids it ever being mistaken for an extra grid
item if that default ever changes.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CatalogProductCardTest`
Expected: PASS

Run: `php artisan test --filter=CatalogRoutingTest`
Run: `php artisan test --filter=CatalogListingLayoutTest`
Expected: both PASS unchanged.

- [ ] **Step 5: Commit**

```bash
git add resources/views/catalog/partials/product-grid-items.blade.php tests/Feature/CatalogProductCardTest.php
git commit -m "feat: restyle catalog product cards with price, MOQ, and Add to RFQ modal"
```

---

## Task 5: Full regression + manual localhost:8000 review gate

No new automated tests — this is the manual verification gate, same pattern
as the Homepage phase's final task. **Do not deploy or push to production as
part of this task; stop after Step 4 and wait for the user's explicit
go-ahead.**

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: every test passes, including all tests from Tasks 1-4 and every
pre-existing test — in particular `CatalogRoutingTest`, `CatalogSearchTest`,
and every Homepage-phase test (this plan touches none of the same files, so
none of those should be affected, but confirm).

- [ ] **Step 2: Confirm there's real catalog data to look at**

The dev database already has published categories/products/custom
attributes from prior work in this project. Spot-check via tinker that at
least one published leaf category has products with at least one custom
attribute set (so the filter sidebar has something to show):

```
php artisan tinker --execute="App\Models\CustomAttribute::whereHasMorph('attributable', [App\Models\Product::class])->count();"
```

If this returns `0`, add one for a quick visual check (optional — the page
works correctly either way, this just makes the filter sidebar visible
during review):

```
php artisan tinker --execute="App\Models\Product::where('status','published')->first()->customAttributes()->create(['label' => 'Material', 'value' => 'Copper']);"
```

- [ ] **Step 3: Start the dev server and review manually**

Run `php artisan serve`, then visit a leaf category's listing page (e.g.
`http://localhost:8000/products/{a-leaf-category-slug}`) and check:
- Product cards show the new Modernist styling, grayscale images, price,
  and MOQ — no supplier name anywhere.
- If any product in the category has a custom attribute, the sidebar shows
  a filter group for it; checking a box reloads the page with results
  narrowed and the box still checked.
- The sort dropdown changes result order; combined with an active filter,
  both stay applied together.
- Scrolling to the bottom of the grid loads more results via the existing
  infinite-scroll behavior, and scrolling further while a filter/sort is
  active keeps returning correctly filtered/sorted results (not the
  unfiltered set).
- Clicking "Add to RFQ" on a card opens that product's quote modal and
  submitting it behaves identically to the product detail page's modal
  (quote number flash message, etc.).
- A category with children still shows its child tiles as before (hub
  behavior unaffected).

- [ ] **Step 4: Report and stop**

Summarize what was checked. **Do not deploy to Railway/production** until
the user has reviewed this themselves and explicitly confirms.
