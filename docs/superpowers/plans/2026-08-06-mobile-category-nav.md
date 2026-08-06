# Mobile Category Drill-Down Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the broken mobile rendering of the public header's "Products" mega-menu (today: the same desktop dropdown crammed into the collapsed mobile nav) with an AFL-Global-inspired drill-down — each level stacks as a list, tapping into a category with children slides to a new screen with a back button.

**Architecture:** A new `CategoryHierarchy::publishedTree()` builds the full, unlimited-depth published category tree in one query. A recursive Blade partial renders one hidden `<div>` panel per tree node (flat siblings, not DOM-nested). Vanilla JS (no build step, matching this project's CDN-based Bootstrap setup) toggles which panel is visible and maintains a simple back-stack. Desktop's existing `.mega-menu` dropdown is untouched in behavior, just scoped to `d-lg-block` so it can't render below the collapse breakpoint anymore.

**Tech Stack:** Laravel 11 Blade (server-rendered, no AJAX for this feature), Bootstrap 5.3.3 (CDN), vanilla JS, existing `App\Support\CategoryHierarchy` helper class.

## Global Constraints

- Desktop (`≥ lg`, 992px) behavior and markup for the mega-menu must not change — see `tests/Feature/MegaMenuTest.php`, which must keep passing unmodified.
- Only `status = 'published'` categories appear at any level — the same rule the desktop mega-menu already enforces (see `MegaMenuTest::test_a_draft_category_never_appears_in_the_mega_menu`).
- No AJAX/lazy loading — the full tree is server-rendered into the page on every request, per the approved design.
- No new JS framework/build step — plain `<script src="...">`, matching every other script tag in `resources/views/layouts/app.blade.php`.
- Tapping a category's name always navigates immediately (whether or not it has children); a separate chevron button drills into subcategories.

---

### Task 1: `CategoryHierarchy::publishedTree()`

**Files:**
- Modify: `app/Support/CategoryHierarchy.php`
- Test: `tests/Feature/CategoryHierarchyTest.php`

**Interfaces:**
- Produces: `CategoryHierarchy::publishedTree(): array` — a list of nodes, each shaped `['id' => int, 'name' => string, 'path' => string, 'children' => array (same shape, recursively)]`, top-level nodes first. Consumed by Task 2 (view composer) and, through that, Task 3 (Blade partial).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/CategoryHierarchyTest.php` (it already has a private `tree()` helper building a 3-level Television → LED → HD tree — reuse it; note `tree()` does not override `slug`, so assert against the created models' actual `slug` values, never a hardcoded guess):

```php
use Illuminate\Support\Facades\DB;
```

```php
    public function test_published_tree_nests_categories_to_full_depth(): void
    {
        ['top' => $top, 'sub' => $sub, 'leaf' => $leaf] = $this->tree();

        $tree = CategoryHierarchy::publishedTree();

        $this->assertSame($top->id, $tree[0]['id']);
        $this->assertSame($top->slug, $tree[0]['path']);
        $this->assertSame($sub->id, $tree[0]['children'][0]['id']);
        $this->assertSame($top->slug.'/'.$sub->slug, $tree[0]['children'][0]['path']);
        $this->assertSame($leaf->id, $tree[0]['children'][0]['children'][0]['id']);
        $this->assertSame($top->slug.'/'.$sub->slug.'/'.$leaf->slug, $tree[0]['children'][0]['children'][0]['path']);
    }

    public function test_published_tree_excludes_draft_categories(): void
    {
        ['top' => $top] = $this->tree();
        Category::factory()->create(['name' => 'Draft Sub', 'parent_id' => $top->id, 'status' => 'draft']);

        $tree = CategoryHierarchy::publishedTree();

        $names = collect($tree[0]['children'])->pluck('name');
        $this->assertNotContains('Draft Sub', $names);
    }

    public function test_published_tree_issues_a_single_query_regardless_of_depth(): void
    {
        $this->tree();

        DB::enableQueryLog();
        CategoryHierarchy::publishedTree();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_published_tree`
Expected: FAIL with "Call to undefined method App\Support\CategoryHierarchy::publishedTree()" for all three

- [ ] **Step 3: Implement `publishedTree()`**

Add to `app/Support/CategoryHierarchy.php` (inside the `CategoryHierarchy` class, alongside `descendantAndSelfIds()`):

```php
    /**
     * The full published category tree, nested to whatever depth the data
     * has. One query total (same flat-fetch-then-build-in-PHP technique as
     * descendantAndSelfIds()) regardless of depth.
     *
     * @return list<array{id: int, name: string, path: string, children: array}>
     */
    public static function publishedTree(): array
    {
        $all = Category::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug']);

        $childrenOf = [];
        foreach ($all as $category) {
            $childrenOf[$category->parent_id ?? 0][] = $category;
        }

        $build = function (?int $parentId, string $parentPath, int $depth) use (&$build, $childrenOf): array {
            if ($depth > 50) {
                return [];
            }

            $nodes = [];
            foreach ($childrenOf[$parentId ?? 0] ?? [] as $category) {
                $path = $parentPath === '' ? $category->slug : $parentPath.'/'.$category->slug;

                $nodes[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => $path,
                    'children' => $build($category->id, $path, $depth + 1),
                ];
            }

            return $nodes;
        };

        return $build(null, '', 0);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CategoryHierarchyTest`
Expected: PASS (all tests in the file, including the pre-existing ones)

- [ ] **Step 5: Commit**

```bash
git add app/Support/CategoryHierarchy.php tests/Feature/CategoryHierarchyTest.php
git commit -m "feat: add CategoryHierarchy::publishedTree() for full-depth category nesting"
```

---

### Task 2: Wire `$categoryTree` into the layout, scope the desktop mega-menu to `lg`+

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/MegaMenuTest.php`

**Interfaces:**
- Consumes: `CategoryHierarchy::publishedTree()` (Task 1).
- Produces: a `$categoryTree` variable available in `layouts.app` (and anything it includes), consumed by Task 3's Blade partial.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/MegaMenuTest.php`:

```php
    public function test_the_desktop_mega_menu_is_hidden_below_the_lg_breakpoint(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('mega-menu p-3 d-none d-lg-block', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_desktop_mega_menu_is_hidden_below_the_lg_breakpoint`
Expected: FAIL (the current markup is `class="dropdown-menu mega-menu p-3"`, no `d-none d-lg-block`)

- [ ] **Step 3: Add `$categoryTree` to the view composer**

In `app/Providers/AppServiceProvider.php`, add the import and the new composer variable:

```php
use App\Support\CategoryHierarchy;
```

```php
            $view->with('categoryTree', CategoryHierarchy::publishedTree());
```

(add this line inside the existing `View::composer('layouts.app', function ($view) { ... })` closure, alongside the existing `$view->with('topLevelCategories', ...)` call — leave that one exactly as-is, desktop keeps using it)

- [ ] **Step 4: Scope the desktop dropdown to `lg`+**

In `resources/views/layouts/app.blade.php`, change:

```blade
                            <a class="nav-link dropdown-toggle" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                            <div class="dropdown-menu mega-menu p-3">
```

to:

```blade
                            <a class="nav-link dropdown-toggle d-none d-lg-block" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                            <div class="dropdown-menu mega-menu p-3 d-none d-lg-block">
```

- [ ] **Step 5: Run the test to verify it passes, and confirm the existing mega-menu tests still pass**

Run: `php artisan test --filter=MegaMenuTest`
Expected: PASS (all 4 tests — the 3 pre-existing plus the new one)

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php resources/views/layouts/app.blade.php tests/Feature/MegaMenuTest.php
git commit -m "feat: scope the desktop mega-menu dropdown to lg breakpoint and up"
```

---

### Task 3: Mobile drill-down markup

**Files:**
- Create: `resources/views/partials/mobile-category-panel.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/MobileCategoryDrillDownTest.php` (new)

**Interfaces:**
- Consumes: `$categoryTree` (Task 2), each node shaped per Task 1's `publishedTree()` return type.
- Produces: markup with `data-mcn` (drill-down root wrapper), `data-mcn-panel="{id}"` per panel (`"root"` for the top panel, `"cat-{category id}"` for every deeper one), `data-mcn-open="{target panel id}"` on every trigger (the mobile "Products" button and every chevron), `data-mcn-back` on every panel's back button. These exact attribute names are consumed by Task 4 (CSS) and Task 5 (JS).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MobileCategoryDrillDownTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCategoryDrillDownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_the_mobile_products_trigger_opens_the_root_panel(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-open="root"', false);
        $response->assertSee('data-mcn-panel="root"', false);
    }

    public function test_a_category_with_children_gets_a_drill_in_chevron(): void
    {
        $hub = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        Category::factory()->create(['name' => 'Aerial', 'parent_id' => $hub->id, 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-open="cat-'.$hub->id.'"', false);
    }

    public function test_a_leaf_category_has_no_drill_in_chevron(): void
    {
        $leaf = Category::factory()->create(['name' => 'Standalone Category', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-mcn-open="cat-'.$leaf->id.'"', false);
    }

    public function test_a_third_level_category_is_reachable(): void
    {
        $top = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        $sub = Category::factory()->create(['name' => 'Aerial', 'parent_id' => $top->id, 'status' => 'published']);
        $leaf = Category::factory()->create(['name' => 'ADSS', 'parent_id' => $sub->id, 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-panel="cat-'.$sub->id.'"', false);
        $response->assertSee('ADSS');
        $response->assertSee('href="'.url('/products/'.$top->slug.'/'.$sub->slug.'/'.$leaf->slug).'"', false);
    }

    public function test_a_draft_category_never_appears_in_the_drill_down(): void
    {
        Category::factory()->create(['name' => 'Hidden Draft Category', 'status' => 'draft']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Hidden Draft Category');
    }

    public function test_a_category_name_links_straight_to_its_page(): void
    {
        $category = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.url('/products/'.$category->slug).'"', false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MobileCategoryDrillDownTest`
Expected: FAIL — none of the `data-mcn-*` markup exists yet

- [ ] **Step 3: Create the recursive panel partial**

Create `resources/views/partials/mobile-category-panel.blade.php`:

```blade
{{-- resources/views/partials/mobile-category-panel.blade.php --}}
@php
    $panelId = $panelId ?? 'root';
    $heading = $heading ?? 'Products';
@endphp
<div class="mcn-panel" data-mcn-panel="{{ $panelId }}">
    <button type="button" class="mcn-back d-flex align-items-center gap-2" data-mcn-back>
        <span aria-hidden="true">&larr;</span> {{ $heading }}
    </button>
    <ul class="list-unstyled mb-0">
        @foreach ($nodes as $node)
            <li class="d-flex align-items-center justify-content-between">
                <a href="{{ url('/products/'.$node['path']) }}" class="mcn-row-link flex-grow-1">{{ $node['name'] }}</a>
                @if (!empty($node['children']))
                    <button type="button" class="mcn-drill-in" data-mcn-open="cat-{{ $node['id'] }}" aria-label="Browse {{ $node['name'] }} subcategories">&rsaquo;</button>
                @endif
            </li>
        @endforeach
    </ul>
</div>

@foreach ($nodes as $node)
    @if (!empty($node['children']))
        @include('partials.mobile-category-panel', [
            'nodes' => $node['children'],
            'panelId' => 'cat-'.$node['id'],
            'heading' => $node['name'],
        ])
    @endif
@endforeach
```

- [ ] **Step 4: Wire the mobile trigger button and the panel wrapper into the layout**

In `resources/views/layouts/app.blade.php`, add the mobile trigger button right after the desktop toggle link changed in Task 2 (still inside the same `<li class="nav-item dropdown">`):

```blade
                            <a class="nav-link dropdown-toggle d-none d-lg-block" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                            <button type="button" class="nav-link mcn-open-trigger d-lg-none" data-mcn-open="root">{{ $item->label }}</button>
                            <div class="dropdown-menu mega-menu p-3 d-none d-lg-block">
```

Then, immediately after the closing `</ul>` of `<ul class="navbar-nav me-auto">` (i.e. right before the `<form class="site-search-form ...">` search form), add the drill-down wrapper:

```blade
                <div class="mcn d-lg-none" data-mcn>
                    @include('partials.mobile-category-panel', ['nodes' => $categoryTree])
                </div>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MobileCategoryDrillDownTest`
Expected: PASS (all 6 tests)

- [ ] **Step 6: Run MegaMenuTest again to confirm desktop is still unaffected**

Run: `php artisan test --filter=MegaMenuTest`
Expected: PASS (all 4 tests)

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/mobile-category-panel.blade.php resources/views/layouts/app.blade.php tests/Feature/MobileCategoryDrillDownTest.php
git commit -m "feat: render the mobile category drill-down panels"
```

---

### Task 4: CSS for panel show/hide

**Files:**
- Modify: `public/css/site.css`
- Test: `tests/Feature/SiteStylesheetTest.php`

**Interfaces:**
- Consumes: the `.mcn-panel`, `.mcn-back`, `.mcn-drill-in`, `.mcn-row-link`, `.mcn-open-trigger`, `.mcn-hidden` class names and `data-mcn-panel`/`data-mcn` attributes from Task 3's markup.
- Produces: the `is-active` and `mcn-hidden` class names, which Task 5's JS toggles at runtime.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/SiteStylesheetTest.php`:

```php
    public function test_the_stylesheet_defines_the_mobile_category_panel_visibility_rules(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.mcn-panel', $css);
        $this->assertStringContainsString('.mcn-panel.is-active', $css);
        $this->assertStringContainsString('.mcn-hidden', $css);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_stylesheet_defines_the_mobile_category_panel_visibility_rules`
Expected: FAIL — none of these selectors exist in `site.css` yet

- [ ] **Step 3: Add the CSS**

Append to `public/css/site.css`:

```css
/* Mobile category drill-down (Products nav item on mobile — see
   resources/views/partials/mobile-category-panel.blade.php). Every panel
   is hidden by default; JS (public/js/mobile-category-nav.js) toggles
   .is-active on exactly one panel at a time and .mcn-hidden on the rest
   of the mobile nav while a drill-down is open. */
.mcn-panel {
    display: none;
}

.mcn-panel.is-active {
    display: block;
}

.mcn-hidden {
    display: none !important;
}

.mcn-open-trigger,
.mcn-back,
.mcn-drill-in {
    border: 0;
    background: none;
    padding: 0.5rem 0;
}

.mcn-open-trigger,
.mcn-back {
    width: 100%;
    text-align: left;
}

.mcn-back {
    font-weight: 600;
}

.mcn-drill-in {
    font-size: 1.25rem;
    line-height: 1;
    padding: 0.25rem 0.5rem;
}

.mcn-row-link {
    display: block;
    padding: 0.5rem 0;
    color: inherit;
    text-decoration: none;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_the_stylesheet_defines_the_mobile_category_panel_visibility_rules`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add public/css/site.css tests/Feature/SiteStylesheetTest.php
git commit -m "feat: style the mobile category drill-down panels"
```

---

### Task 5: JS panel-switching interaction

**Files:**
- Create: `public/js/mobile-category-nav.js`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/MobileCategoryDrillDownTest.php`

**Interfaces:**
- Consumes: `data-mcn` (root wrapper), `data-mcn-panel`, `data-mcn-open`, `data-mcn-back` from Task 3; `.mcn-panel`, `.is-active`, `.mcn-hidden` from Task 4.
- Produces: no PHP-visible interface — this is the runtime behavior layer. Verified by the presence of the script tag and by asserting the file's content defines the expected hooks (this test suite has no JS execution environment, so this is a content/regression guard, not a behavioral test — see the plan's final manual-verification note).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/MobileCategoryDrillDownTest.php`:

```php
    public function test_the_mobile_nav_script_is_linked_and_defines_the_panel_switching_hooks(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('js/mobile-category-nav.js', false);

        $js = file_get_contents(public_path('js/mobile-category-nav.js'));
        $this->assertStringContainsString('data-mcn-open', $js);
        $this->assertStringContainsString('data-mcn-back', $js);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_mobile_nav_script_is_linked_and_defines_the_panel_switching_hooks`
Expected: FAIL — `public/js/mobile-category-nav.js` doesn't exist yet, and isn't linked

- [ ] **Step 3: Create the JS file**

Create `public/js/mobile-category-nav.js`:

```javascript
(function () {
    var mainNav = document.getElementById('mainNav');
    var mcn = mainNav ? mainNav.querySelector('[data-mcn]') : null;

    if (!mainNav || !mcn) {
        return;
    }

    var siblings = Array.prototype.filter.call(mainNav.children, function (el) {
        return el !== mcn;
    });

    var stack = [];

    function showPanel(panelId) {
        mcn.querySelectorAll('.mcn-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.mcnPanel === panelId);
        });
    }

    function openDrillDown(panelId) {
        siblings.forEach(function (el) {
            el.classList.add('mcn-hidden');
        });
        mcn.classList.add('is-active');
        showPanel(panelId);
    }

    function closeDrillDown() {
        siblings.forEach(function (el) {
            el.classList.remove('mcn-hidden');
        });
        mcn.classList.remove('is-active');
        stack = [];
    }

    mainNav.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-mcn-open]');
        if (opener) {
            var current = mcn.querySelector('.mcn-panel.is-active');
            if (current) {
                stack.push(current.dataset.mcnPanel);
            }
            if (!mcn.classList.contains('is-active')) {
                openDrillDown(opener.getAttribute('data-mcn-open'));
            } else {
                showPanel(opener.getAttribute('data-mcn-open'));
            }
            return;
        }

        var back = event.target.closest('[data-mcn-back]');
        if (back) {
            var previous = stack.pop();
            if (previous) {
                showPanel(previous);
            } else {
                closeDrillDown();
            }
        }
    });
})();
```

- [ ] **Step 4: Link the script in the layout**

In `resources/views/layouts/app.blade.php`, immediately after the existing Bootstrap bundle `<script>` tag (`<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>`), add:

```blade
    <script src="{{ asset('js/mobile-category-nav.js') }}"></script>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_the_mobile_nav_script_is_linked_and_defines_the_panel_switching_hooks`
Expected: PASS

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test`
Expected: PASS — confirms nothing in this feature broke `MegaMenuTest`, `CategoryHierarchyTest`, or anything else

- [ ] **Step 7: Commit**

```bash
git add public/js/mobile-category-nav.js resources/views/layouts/app.blade.php tests/Feature/MobileCategoryDrillDownTest.php
git commit -m "feat: add mobile category drill-down panel-switching interaction"
```

---

## Manual Verification (after all tasks)

This test suite has no browser/JS execution — every automated test above checks markup, CSS text, and JS file content, not actual runtime interaction. Verify by hand in a real browser before calling this done:

1. `npm`/asset build isn't in play here (no bundler) — just load the site locally and resize the browser below ~992px width (or open dev tools' mobile device emulation).
2. Confirm the search box no longer overflows the viewport (Task from the earlier batch of fixes).
3. Tap the hamburger to open the mobile nav, then tap "Products" — confirm the root category list appears and the rest of the mobile menu (search box, login/register links) disappears while it's open.
4. Tap a category with subcategories (chevron visible) — confirm it slides to that category's own list with a "← [Category Name]" back button.
5. Tap "Back" repeatedly — confirm it returns one level at a time, and from the root panel, returns all the way to the normal mobile menu (search box and links reappear).
6. Tap a category's name (not the chevron) at any level — confirm it navigates directly to that category's page.
7. Resize back above ~992px — confirm the desktop hover mega-menu still works exactly as before.
