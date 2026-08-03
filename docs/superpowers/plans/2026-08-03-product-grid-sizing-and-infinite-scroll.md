# Product Grid Sizing & Progressive Loading Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On Category/Sub-Category pages, cap each product card to 332px × 308px (3 per row, matching the existing `row-cols-md-3` layout), load only the first 3 rows (9 products) up front, and load further rows progressively on scroll with a loading indicator — instead of rendering every published product in the category at once.

**Architecture:** `CatalogController::show()` switches the category's product listing from `->get()` to `->paginate(9)`. The card markup is extracted into a small partial (`catalog/partials/product-grid-items.blade.php`) shared between the normal full-page render and a new AJAX code path: when the same route is hit with an `X-Requested-With: XMLHttpRequest` header (Laravel's `Request::ajax()`), the controller returns just that partial (plus an `X-Next-Page-Url` response header) instead of the full page. Client-side, an `IntersectionObserver` watches a sentinel element at the bottom of the grid; when it's visible, it fetches the next page URL with that header, appends the returned card markup, and updates the sentinel from the response header — stopping once there's no next page.

**Tech Stack:** Laravel pagination (`LengthAwarePaginator`), Blade partials, vanilla JS `IntersectionObserver` + `fetch()` (no bundler in this app — inline `<script>`, consistent with the rest of the codebase).

## Global Constraints

- Test-first, `php artisan test` passing before every commit (`CLAUDE.md`).
- `CatalogController` remains the single controller for every catalog depth (`CLAUDE.md`'s architecture map) — this plan does not introduce a second controller or a per-depth template; the AJAX branch is a conditional return inside the same `show()` method.
- Commit frequently in small units.

---

## File Structure

- Modify: `app/Http/Controllers/CatalogController.php` — paginate at 9, add the AJAX-partial branch.
- Create: `resources/views/catalog/partials/product-grid-items.blade.php` — just the `<div class="col">` card fragments (shared by both response types).
- Modify: `resources/views/catalog/category.blade.php` — use the partial, add sentinel/loader markup + `IntersectionObserver` JS.
- Modify: `public/css/site.css` — `.product-card` fixed sizing.
- Modify: `tests/Feature/CatalogRoutingTest.php` — pagination behavior tests.

---

### Task 1: Paginate the category product listing

**Files:**
- Modify: `app/Http/Controllers/CatalogController.php`
- Test: `tests/Feature/CatalogRoutingTest.php`

**Interfaces:**
- Produces: `catalog.category` view's `products` variable is now a `LengthAwarePaginator` (9 per page) when a category is resolved, an empty `Collection` when not (unchanged — the top-level Products hub never lists products, only child categories, per the existing ternary).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CatalogRoutingTest.php` (read the file first — it wasn't fully read during planning — to match its existing category/product fixture-creation helpers and `use` imports before adding this):

```php
    public function test_a_category_page_shows_only_the_first_nine_published_products(): void
    {
        $category = \App\Models\Category::factory()->create(['status' => 'published']);
        \App\Models\Product::factory()->count(12)->create([
            'category_id' => $category->id,
            'status' => 'published',
        ]);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->count() === 9);
    }

    public function test_a_second_page_of_products_can_be_fetched_via_ajax(): void
    {
        $category = \App\Models\Category::factory()->create(['status' => 'published']);
        \App\Models\Product::factory()->count(12)->create([
            'category_id' => $category->id,
            'status' => 'published',
        ]);

        $response = $this->get('/products/'.$category->slug.'?page=2', ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->count() === 3);
        // The AJAX branch renders only the card fragments, not the full page chrome.
        $response->assertDontSee('<nav', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogRoutingTest`
Expected: the two new tests FAIL — `products` currently holds all 12 (no pagination), and there's no AJAX-partial branch yet.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/CatalogController.php`, add `use Illuminate\View\View;` is already imported; change the `products` line and add the AJAX branch. Replace the final `return view('catalog.category', [...])` block:

```php
        $products = $category
            ? $category->products()->where('status', 'published')->orderBy('sort_order')->paginate(9)
            : collect();

        if ($request->ajax()) {
            return view('catalog.partials.product-grid-items', [
                'products' => $products,
                'breadcrumb' => $breadcrumb,
            ])->header('X-Next-Page-Url', $products instanceof \Illuminate\Contracts\Pagination\Paginator ? (string) $products->nextPageUrl() : '');
        }

        return view('catalog.category', [
            'category' => $category,
            'breadcrumb' => $breadcrumb,
            'children' => $category
                ? $category->children()->where('status', 'published')->get()
                : Category::query()->whereNull('parent_id')->where('status', 'published')->orderBy('sort_order')->get(),
            'products' => $products,
        ]);
```

(The `view(...)->header(...)` call returns an `Illuminate\Http\Response`, not a `View` — this changes the method's effective return type for that branch. Update the method signature from `: View` to `: View|\Illuminate\Http\Response` to keep the type declaration accurate.)

Note: `catalog.partials.product-grid-items` doesn't exist yet — Task 2 creates it. This task's tests will still fail after this step until Task 2 lands; that's expected — the two tasks are sequential, not independently shippable, because the AJAX test needs the partial to render at all. Proceed to Task 2 before re-running.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/CatalogController.php tests/Feature/CatalogRoutingTest.php
git commit -m "Paginate category product listings at 9 per page and add an AJAX partial branch"
```

(Commit here even though tests aren't green yet — Task 2 is the very next task and completes this unit of work. If your workflow requires green-before-commit, hold this commit and squash with Task 2's instead.)

---

### Task 2: Extract the card partial, apply fixed sizing, and finish the AJAX response

**Files:**
- Create: `resources/views/catalog/partials/product-grid-items.blade.php`
- Modify: `resources/views/catalog/category.blade.php`
- Modify: `public/css/site.css`
- Test: `tests/Feature/CatalogRoutingTest.php` (from Task 1 — now passes)

**Interfaces:**
- Consumes: `$products` (Paginator or Collection), `$breadcrumb` (array of `Category`) — same shape category.blade.php already receives.
- Produces: `.product-card` CSS class, applied to every product card, capped at 332×308px.

- [ ] **Step 1: Create the card-items partial**

`resources/views/catalog/partials/product-grid-items.blade.php` (this is the body of the existing `@if ($products->isNotEmpty()) ... @endif` block in `category.blade.php`, lines 46-62, with the card given a `.product-card` class):

```blade
{{-- resources/views/catalog/partials/product-grid-items.blade.php --}}
@foreach ($products as $product)
    <div class="col">
        <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="card product-card h-100 text-decoration-none">
            @if ($product->images->first())
                <img src="{{ asset('storage/'.$product->images->first()->path) }}" class="card-img-top product-card-img" alt="{{ $product->name }}">
            @endif
            <div class="card-body">
                <h5 class="card-title text-dark product-card-title">{{ $product->name }}</h5>
                <p class="card-text text-muted product-card-desc">{{ $product->short_description }}</p>
            </div>
        </a>
    </div>
@endforeach
```

- [ ] **Step 2: Use the partial in `category.blade.php`, add sentinel + loader**

In `resources/views/catalog/category.blade.php`, replace the `@if ($products->isNotEmpty()) ... @endif` block (lines 46-62) with:

```blade
    @if ($products->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-2" id="product-grid">
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
```

- [ ] **Step 3: Add the fixed card sizing to `site.css`**

Append to `public/css/site.css`:

```css
.product-card {
    width: 332px;
    max-width: 100%;
    height: 308px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
}

.product-card-img {
    height: 160px;
    object-fit: cover;
}

.product-card-title {
    font-size: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-card-desc {
    font-size: 0.85rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

- [ ] **Step 4: Run the tests from Task 1 to verify they now pass**

Run: `php artisan test --filter=CatalogRoutingTest`
Expected: PASS (all tests, including the two new ones from Task 1)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all PASS — this is the last task in this plan.

- [ ] **Step 6: Commit**

```bash
git add resources/views/catalog/partials/product-grid-items.blade.php resources/views/catalog/category.blade.php public/css/site.css
git commit -m "Cap product cards to 332x308 and load further rows progressively on scroll"
```

---

## Self-Review Notes

- **Spec coverage:** 332×308px cards ✓ (Task 2 CSS), 3 per row ✓ (unchanged `row-cols-md-3`), first load = first 3 rows ✓ (`paginate(9)`, Task 1), progressive loading with a loader on scroll ✓ (Task 2 `IntersectionObserver` + spinner).
- **Single controller for every catalog depth preserved:** the AJAX branch is a conditional inside the existing `CatalogController::show()`, not a new controller — matches `CLAUDE.md`'s explicit "there is deliberately no per-depth template" rule, extended here to "no per-response-type controller" either.
- **No placeholders:** all steps have complete code.
- **Manual verification needed:** the actual scroll-triggered loading UX (spinner timing, smoothness) can't be asserted by PHPUnit — verify in a browser with a category seeded with >9 products after implementation.
