# Product Images, Related Products, Seller Login & Edit-Acceptance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the seller-portal login crash, enforce single-primary product images with a
hero/thumbnail carousel and consistent 132×132px thumbnails everywhere a product image
appears, fix the non-clickable related-products bug, add a seller self-registration link
to the seller login page, and build the admin-edit / seller-acceptance workflow around
initial product review.

**Architecture:** Four independent groups of changes to an existing Laravel 11 +
Filament v3 app. Group B (login fix) is a one-line model change. Group A (images)
introduces one model event, one accessor, and one reusable Blade component consumed by
four view files. Group C (seller discoverability) uses a Filament render hook — no new
routes. Group D (edit-acceptance workflow) adds one new table/model, two new Mailables,
and extends the existing Admin/Seller `ProductResource` classes with a new
`pending_seller_acceptance` status.

**Tech Stack:** Laravel 11, PHP 8.2, Filament v3 (two panels: `admin`/`staff` guard,
`seller`/`seller` guard), MySQL (dev) / SQLite in-memory (tests, per `phpunit.xml`),
Bootstrap 5 (CDN).

## Global Constraints

- Full design: `docs/superpowers/specs/2026-07-15-catalog-fixes-and-seller-workflow-design.md`
  — read before deviating.
- TDD throughout: write the failing test before the implementation, for every task.
- Commit after every task.
- `APP_TIMEZONE=Asia/Kolkata`; tests run against SQLite in-memory (`phpunit.xml`), never
  the dev MySQL database — don't touch that config.
- Thumbnail size is fixed at **132×132px** (`object-fit: cover`), applied via one shared
  Blade component (`<x-product-thumbnail>`), never re-implemented inline per view.
- The admin-edit/seller-acceptance gate (Group D) fires **only** when a product's status
  is `pending_review` at the moment Admin saves an edit. Edits to already-`published`
  products (Content Editor cleanup, per `CLAUDE.md`) are completely untouched by this
  plan — do not add any gating logic to that path.
- Tracked fields for the edit-trail diff: `name`, `slug`, `sku`, `short_description`,
  `description`, `features`, `applications`, `spec_sheet_path`, `category_id`,
  `quantity`. Never `price_display` or `status` — those are Admin-only and must never
  trigger the seller-acceptance gate by themselves.
- Every best-effort email send (this codebase's established convention) wraps
  `Mail::to(...)->send(...)` in `try/catch (\Throwable $exception)`, logs via
  `Illuminate\Support\Facades\Log::error()`, and never lets a mail failure block the
  triggering action.
- `products.status` is a plain `string` column (no DB enum) — adding
  `pending_seller_acceptance` needs no migration, only code-side updates (Filament form
  options/validation, action visibility).

---

## Group B: Seller Login Crash

### Task 1: Fix the `Filament\FilamentManager::getUserName()` crash on seller login

**Files:**
- Modify: `app/Models/Seller.php`
- Test: `tests/Feature/SellerLoginTest.php`

**Interfaces:**
- Produces: `Seller::getFilamentName(): string` (satisfies Filament's `HasName`
  contract), returning `contact_person`, falling back to `company_name` if blank.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SellerLoginTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_load_the_dashboard_after_logging_in(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'contact_person' => 'Asha Rao',
        ]);

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SellerLoginTest`
Expected: FAIL — 500 Internal Server Error (`TypeError: Filament\FilamentManager::getUserName(): Return value must be of type string, null returned`), reproducing the exact crash reported against `nhessel@example.com`.

- [ ] **Step 3: Implement the fix**

In `app/Models/Seller.php`, add the import and interface:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
```

```php
class Seller extends Authenticatable implements FilamentUser, HasName
{
```

Add the method (alongside `canAccessPanel()`):

```php
public function getFilamentName(): string
{
    return $this->contact_person ?: $this->company_name;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SellerLoginTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS (153 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Seller.php tests/Feature/SellerLoginTest.php
git commit -m "Fix seller portal login crash: implement Filament's HasName on Seller"
```

---

## Group A: Product Images

### Task 2: Single-primary-image enforcement on `ProductImage`

**Files:**
- Modify: `app/Models/ProductImage.php`
- Test: `tests/Feature/ProductImagePrimaryTest.php`

**Interfaces:**
- Produces: saving a `ProductImage` with `is_primary = true` unsets `is_primary` on
  every sibling `ProductImage` row (same `product_id`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductImagePrimaryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImagePrimaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_image_primary_unsets_primary_on_sibling_images(): void
    {
        $product = Product::factory()->create();
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);

        $second->update(['is_primary' => true]);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_marking_an_image_primary_does_not_affect_another_products_images(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $otherImage = $otherProduct->images()->create(['path' => 'product-images/other.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $product->images()->create(['path' => 'product-images/own.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $this->assertTrue($otherImage->fresh()->is_primary);
    }

    public function test_creating_a_second_primary_image_directly_also_unsets_the_first(): void
    {
        $product = Product::factory()->create();
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->assertFalse($first->fresh()->is_primary);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductImagePrimaryTest`
Expected: FAIL — multiple images can hold `is_primary = true` simultaneously.

- [ ] **Step 3: Implement the model event**

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

    protected static function booted(): void
    {
        static::saved(function (ProductImage $image) {
            if ($image->is_primary) {
                static::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductImagePrimaryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/ProductImage.php tests/Feature/ProductImagePrimaryTest.php
git commit -m "Enforce a single primary image per product"
```

---

### Task 3: `Product::primaryImage()` fallback accessor

**Files:**
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/ProductPrimaryImageAccessorTest.php`

**Interfaces:**
- Consumes: `Product::images(): HasMany` (already exists, ordered by `sort_order`).
- Produces: `Product::primaryImage(): ?ProductImage` — the flagged-primary image, or the
  first image by `sort_order` if none is flagged, or `null` if the product has no
  images. This is the single method every later task (hero carousel, related products,
  featured blocks, RFQ email) uses to resolve "the" product image.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductPrimaryImageAccessorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPrimaryImageAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_flagged_primary_image(): void
    {
        $product = Product::factory()->create();
        $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $primary = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->assertTrue($product->primaryImage()->is($primary));
    }

    public function test_it_falls_back_to_the_first_image_by_sort_order_when_none_is_flagged(): void
    {
        $product = Product::factory()->create();
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => false]);

        $this->assertTrue($product->primaryImage()->is($first));
    }

    public function test_it_returns_null_when_the_product_has_no_images(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->primaryImage());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductPrimaryImageAccessorTest`
Expected: FAIL — `Product::primaryImage()` does not exist.

- [ ] **Step 3: Implement the accessor**

In `app/Models/Product.php`, add (alongside the other public methods):

```php
public function primaryImage(): ?ProductImage
{
    return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
}
```

(`ProductImage` is in the same `App\Models` namespace — no new `use` statement needed.
`$this->images` uses the already-defined `images(): HasMany` relation, ordered by
`sort_order`, so the fallback naturally picks the earliest image.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductPrimaryImageAccessorTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Product.php tests/Feature/ProductPrimaryImageAccessorTest.php
git commit -m "Add Product::primaryImage() fallback accessor"
```

---

### Task 4: Shared `<x-product-thumbnail>` Blade component (132×132px)

**Files:**
- Create: `resources/views/components/product-thumbnail.blade.php`
- Test: `tests/Feature/ProductThumbnailComponentTest.php`

**Interfaces:**
- Produces: `<x-product-thumbnail :path="$path" :alt="$alt" />` — renders a
  `132×132px`, `object-fit: cover` `<img>` when `$path` is a non-empty storage-relative
  path, renders nothing when `$path` is `null`/empty. Takes a plain path string (not a
  model) so it works identically for `ProductImage::$path` and any other
  storage-relative image path in the app.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductThumbnailComponentTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductThumbnailComponentTest extends TestCase
{
    public function test_it_renders_a_fixed_size_image_when_given_a_path(): void
    {
        $html = Blade::render('<x-product-thumbnail path="product-images/cable.jpg" alt="Test Product" />');

        $this->assertStringContainsString('width="132"', $html);
        $this->assertStringContainsString('height="132"', $html);
        $this->assertStringContainsString('product-images/cable.jpg', $html);
        $this->assertStringContainsString('alt="Test Product"', $html);
    }

    public function test_it_renders_nothing_when_given_no_path(): void
    {
        $html = Blade::render('<x-product-thumbnail :path="null" alt="Test Product" />');

        $this->assertSame('', trim($html));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductThumbnailComponentTest`
Expected: FAIL — component does not exist.

- [ ] **Step 3: Implement the component**

`resources/views/components/product-thumbnail.blade.php`:

```blade
@props(['path' => null, 'alt' => ''])

@if ($path)
    <img
        src="{{ asset('storage/'.$path) }}"
        alt="{{ $alt }}"
        width="132"
        height="132"
        style="width:132px;height:132px;object-fit:cover;"
        {{ $attributes->merge(['class' => 'rounded']) }}
    >
@endif
```

(Laravel auto-discovers anonymous components under `resources/views/components/` — no
class or manual registration needed. Inline `width`/`height` attributes are included
alongside the CSS because email clients, unlike browsers, often ignore CSS sizing.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductThumbnailComponentTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/product-thumbnail.blade.php tests/Feature/ProductThumbnailComponentTest.php
git commit -m "Add shared 132x132px product-thumbnail Blade component"
```

---

### Task 5: Hero image carousel with thumbnail navigation on the product page

**Files:**
- Modify: `resources/views/catalog/product.blade.php:19-23` (the inner `<div
  class="col-md-6">` image block, inside the outer `<div class="row">` that starts on
  line 18 — leave the outer `row` div and its sibling `col-md-6` untouched)
- Test: `tests/Feature/ProductHeroCarouselTest.php`

**Interfaces:**
- Consumes: `Product::primaryImage()` (Task 3), `<x-product-thumbnail>` (Task 4).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductHeroCarouselTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductHeroCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_primary_image_renders_first_as_the_active_carousel_slide(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/secondary.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $product->images()->create(['path' => 'product-images/primary.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $html = $response->getContent();
        $positionOfPrimary = strpos($html, 'primary.jpg');
        $positionOfSecondary = strpos($html, 'secondary.jpg');
        $this->assertNotFalse($positionOfPrimary);
        $this->assertLessThan($positionOfSecondary, $positionOfPrimary, 'Primary image should render first, as the active hero slide.');
    }

    public function test_multiple_images_render_thumbnail_navigation_buttons(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/one.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $product->images()->create(['path' => 'product-images/two.jpg', 'sort_order' => 1, 'is_primary' => false]);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('data-bs-slide-to="0"', false);
        $response->assertSee('data-bs-slide-to="1"', false);
    }

    public function test_a_product_with_no_images_renders_without_a_carousel(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertDontSee('carousel-item', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductHeroCarouselTest`
Expected: FAIL — the product page currently just stacks every image full-width with no
carousel markup.

- [ ] **Step 3: Replace the image column**

In `resources/views/catalog/product.blade.php`, replace lines 19-23 (the `<div
class="col-md-6">...images loop...</div>` block — the first `col-md-6`, containing the
`@foreach ($product->images as $image)` loop) with:

```blade
        <div class="col-md-6">
            @php
                $primaryImage = $product->primaryImage();
                $orderedImages = $product->images->isNotEmpty()
                    ? $product->images->sortBy(fn ($image) => $primaryImage && $image->is($primaryImage) ? 0 : 1)->values()
                    : $product->images;
            @endphp
            @if ($orderedImages->isNotEmpty())
                <div id="productImagesCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                    <div class="carousel-inner rounded-3">
                        @foreach ($orderedImages as $image)
                            <div class="carousel-item @if ($loop->first) active @endif">
                                <img src="{{ asset('storage/'.$image->path) }}" class="d-block w-100" style="max-height: 480px; object-fit: cover;" alt="{{ $product->name }}">
                            </div>
                        @endforeach
                    </div>
                    @if ($orderedImages->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    @endif
                </div>
                @if ($orderedImages->count() > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($orderedImages as $image)
                            <button type="button" data-bs-target="#productImagesCarousel" data-bs-slide-to="{{ $loop->index }}" class="btn p-0 border-0 bg-transparent">
                                <x-product-thumbnail :path="$image->path" :alt="$product->name" />
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductHeroCarouselTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/catalog/product.blade.php tests/Feature/ProductHeroCarouselTest.php
git commit -m "Add hero image carousel with thumbnail navigation to the product page"
```

---

### Task 6: Fix the non-clickable related-products cards

**Files:**
- Modify: `resources/views/catalog/product.blade.php:94-102` (line numbers shift after Task 5 — locate the `Related Products` `@foreach` block)
- Test: `tests/Feature/RelatedProductsTest.php`

**Interfaces:**
- Consumes: `Product::primaryImage()` (Task 3), `<x-product-thumbnail>` (Task 4).

- [ ] **Step 1: Write the failing test**

`tests/Feature/RelatedProductsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_related_product_card_links_to_its_own_page(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $related = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Related Widget']);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('href="'.url('/products/'.$related->path()).'"', false);
    }

    public function test_a_related_product_card_renders_a_fixed_size_thumbnail(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $related = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $related->images()->create(['path' => 'product-images/related-thumb.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('related-thumb.jpg', false);
        $response->assertSee('width="132"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RelatedProductsTest`
Expected: FAIL — no `<a>` tag and no image in the related-products cards.

- [ ] **Step 3: Fix the related-products loop**

In `resources/views/catalog/product.blade.php`, find the `Related Products` block and
replace its `@foreach` body:

```blade
    @if ($related->isNotEmpty())
        <h5 class="mt-4">Related Products</h5>
        <div class="row row-cols-1 row-cols-md-4 g-4">
            @foreach ($related as $relatedProduct)
                <div class="col">
                    <a href="{{ url('/products/'.$relatedProduct->path()) }}" class="card h-100 text-decoration-none">
                        <x-product-thumbnail :path="optional($relatedProduct->primaryImage())->path" :alt="$relatedProduct->name" class="card-img-top" />
                        <div class="card-body">
                            <h6 class="card-title text-dark">{{ $relatedProduct->name }}</h6>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RelatedProductsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/catalog/product.blade.php tests/Feature/RelatedProductsTest.php
git commit -m "Fix non-clickable related-products cards; add thumbnail images"
```

---

### Task 7: Consistent thumbnail on the homepage Featured Products block

**Files:**
- Modify: `resources/views/blocks/featured_products.blade.php:19-21`
- Modify (add a test method): `tests/Feature/PageBlockRenderingTest.php`

**Interfaces:**
- Consumes: `Product::primaryImage()` (Task 3), `<x-product-thumbnail>` (Task 4).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PageBlockRenderingTest.php` (inside the existing
`PageBlockRenderingTest` class — imports for `Category`, `Page`, `Product` already
present at the top of the file):

```php
    public function test_a_featured_products_block_renders_a_fixed_size_thumbnail(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/featured.jpg', 'sort_order' => 0, 'is_primary' => true]);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$product->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('featured.jpg', false);
        $response->assertSee('width="132"', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: FAIL — the new test fails (`card-img-top` renders full-width, not
`132×132px`); the other tests in this file keep passing.

- [ ] **Step 3: Update the block**

In `resources/views/blocks/featured_products.blade.php`, replace lines 19-21:

```blade
                    @if ($product->images->first())
                        <img src="{{ asset('storage/'.$product->images->first()->path) }}" class="card-img-top" alt="{{ $product->name }}">
                    @endif
```

with:

```blade
                    <x-product-thumbnail :path="optional($product->primaryImage())->path" :alt="$product->name" class="card-img-top" />
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PageBlockRenderingTest`
Expected: PASS (all tests in the file, including the pre-existing ones)

- [ ] **Step 5: Commit**

```bash
git add resources/views/blocks/featured_products.blade.php tests/Feature/PageBlockRenderingTest.php
git commit -m "Use the shared 132x132px thumbnail on the homepage Featured Products block"
```

---

### Task 8: Product thumbnail + page link in the RFQ notification email

**Files:**
- Modify: `resources/views/emails/quote-request-received.blade.php`
- Modify (add a test method): `tests/Feature/QuoteRequestMailTest.php`

**Interfaces:**
- Consumes: `Product::primaryImage()` (Task 3), `<x-product-thumbnail>` (Task 4).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/QuoteRequestMailTest.php` (inside the existing
`QuoteRequestMailTest` class):

```php
    public function test_the_notification_email_includes_the_products_thumbnail_and_a_link_to_its_page(): void
    {
        $category = \App\Models\Category::factory()->create(['status' => 'published']);
        $product = \App\Models\Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/quote-thumb.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('quote-thumb.jpg');
        $mailable->assertSeeInHtml('width="132"');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }
```

(`use RefreshDatabase` is already applied to the class, so the factory calls here work
without further setup.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuoteRequestMailTest`
Expected: FAIL — the RFQ email currently renders no image and no product-page link at
all, just the product name as plain text.

- [ ] **Step 3: Update the email view**

In `resources/views/emails/quote-request-received.blade.php`, replace:

```blade
@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
@endif
```

with:

```blade
@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
    <x-product-thumbnail :path="optional($quoteRequest->product->primaryImage())->path" :alt="$quoteRequest->product->name" />
    <p><a href="{{ url('/products/'.$quoteRequest->product->path()) }}">View Product Page</a></p>
@endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuoteRequestMailTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/emails/quote-request-received.blade.php tests/Feature/QuoteRequestMailTest.php
git commit -m "Add product thumbnail and page link to the RFQ notification email"
```

---

## Group C: Seller Discoverability

### Task 9: Seller self-registration link on the Filament seller login page

**Files:**
- Modify: `app/Providers/Filament/SellerPanelProvider.php`
- Test: `tests/Feature/SellerRegisterLinkTest.php`

**Interfaces:**
- Consumes: existing `seller.register` named route (`routes/web.php:28`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/SellerRegisterLinkTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SellerRegisterLinkTest extends TestCase
{
    public function test_the_seller_login_page_links_to_self_registration(): void
    {
        $response = $this->get(route('filament.seller.auth.login'));

        $response->assertOk();
        $response->assertSee(route('seller.register'), false);
    }

    public function test_the_admin_login_page_does_not_show_the_seller_registration_link(): void
    {
        $response = $this->get(route('filament.admin.auth.login'));

        $response->assertOk();
        $response->assertDontSee(route('seller.register'), false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SellerRegisterLinkTest`
Expected: FAIL — the first test fails (no link on the seller login page); the second
passes trivially (nothing links to it anywhere yet).

- [ ] **Step 3: Register the render hook**

In `app/Providers/Filament/SellerPanelProvider.php`, add the imports:

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
```

Add a `boot()` method to the class (alongside the existing `panel()` method):

```php
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): Htmlable => new HtmlString(
                '<p class="mt-4 text-center">New seller? <a href="'.route('seller.register').'">Register here</a></p>'
            ),
            scopes: 'seller',
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SellerRegisterLinkTest`
Expected: PASS (both tests — the `scopes: 'seller'` argument confines the hook to the
seller panel, so it never renders on `/admin/login`)

- [ ] **Step 5: Commit**

```bash
git add app/Providers/Filament/SellerPanelProvider.php tests/Feature/SellerRegisterLinkTest.php
git commit -m "Add a self-registration link to the seller panel login page"
```

---

## Group D: Admin Edit-Trail & Seller Acceptance

### Task 10: `product_edit_trails` table, `ProductEditTrail` model, `Product` relations

**Files:**
- Create: `database/migrations/2026_07_15_120000_create_product_edit_trails_table.php`
- Create: `app/Models/ProductEditTrail.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/ProductEditTrailTest.php`

**Interfaces:**
- Produces: `Product::editTrails(): HasMany`, `Product::latestPendingEditTrail(): ?ProductEditTrail`
  (the most recent trail row with `accepted_at IS NULL`). `ProductEditTrail::$changes`
  cast to `array`, `$accepted_at` cast to `datetime`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductEditTrailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductEditTrail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_have_edit_trails(): void
    {
        $product = Product::factory()->create();
        $staff = Staff::factory()->create();

        $trail = $product->editTrails()->create([
            'staff_id' => $staff->id,
            'changes' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
        ]);

        $this->assertInstanceOf(ProductEditTrail::class, $product->editTrails->first());
        $this->assertSame(['name' => ['old' => 'Old Name', 'new' => 'New Name']], $trail->fresh()->changes);
        $this->assertTrue($trail->staff->is($staff));
    }

    public function test_latest_pending_edit_trail_returns_only_the_most_recent_unaccepted_trail(): void
    {
        $product = Product::factory()->create();

        $product->editTrails()->create(['changes' => ['name' => ['old' => 'A', 'new' => 'B']], 'accepted_at' => now()]);
        $pending = $product->editTrails()->create(['changes' => ['name' => ['old' => 'B', 'new' => 'C']]]);

        $this->assertTrue($product->latestPendingEditTrail()->is($pending));
    }

    public function test_latest_pending_edit_trail_returns_null_when_there_is_none(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->latestPendingEditTrail());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductEditTrailTest`
Expected: FAIL — `product_edit_trails` table and `ProductEditTrail` model don't exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_15_120000_create_product_edit_trails_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_edit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->json('changes');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_edit_trails');
    }
};
```

Run: `php artisan migrate` (applies to the local dev MySQL database — the test suite
picks up new migrations automatically against SQLite in-memory on every `php artisan
test` run).

- [ ] **Step 4: Write the model**

`app/Models/ProductEditTrail.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEditTrail extends Model
{
    protected $fillable = ['product_id', 'staff_id', 'changes', 'accepted_at'];

    protected $casts = [
        'changes' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
```

- [ ] **Step 5: Add the relations to `Product`**

In `app/Models/Product.php`, add the import:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

(Already imported for `images()`/`quoteRequests()` — no change needed if already
present; confirm before adding a duplicate `use` line.)

Add the two methods (alongside `quoteRequests()`):

```php
public function editTrails(): HasMany
{
    return $this->hasMany(ProductEditTrail::class);
}

public function latestPendingEditTrail(): ?ProductEditTrail
{
    return $this->editTrails()->whereNull('accepted_at')->latest()->first();
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProductEditTrailTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_15_120000_create_product_edit_trails_table.php \
        app/Models/ProductEditTrail.php \
        app/Models/Product.php \
        tests/Feature/ProductEditTrailTest.php
git commit -m "Add product_edit_trails table, ProductEditTrail model, and Product relations"
```

---

### Task 11: `ProductEditReadyForAcceptance` mailable

**Files:**
- Create: `app/Mail/ProductEditReadyForAcceptance.php`
- Create: `resources/views/emails/product-edit-ready-for-acceptance.blade.php`
- Test: `tests/Feature/ProductEditReadyForAcceptanceMailTest.php`

**Interfaces:**
- Produces: `App\Mail\ProductEditReadyForAcceptance` (constructed with `Product $product,
  ProductEditTrail $editTrail`), used by Task 13's admin edit-trail creation.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductEditReadyForAcceptanceMailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\ProductEditReadyForAcceptance;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditReadyForAcceptanceMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_lists_the_changed_fields(): void
    {
        $product = Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $trail = $product->editTrails()->create([
            'changes' => [
                'short_description' => ['old' => 'Old text', 'new' => 'New text'],
            ],
        ]);

        $mailable = new ProductEditReadyForAcceptance($product, $trail);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml('Old text');
        $mailable->assertSeeInHtml('New text');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductEditReadyForAcceptanceMailTest`
Expected: FAIL — the class doesn't exist.

- [ ] **Step 3: Write the mailable**

`app/Mail/ProductEditReadyForAcceptance.php`:

```php
<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\ProductEditTrail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductEditReadyForAcceptance extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product, public ProductEditTrail $editTrail)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review changes to your listing: '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.product-edit-ready-for-acceptance',
            with: [
                'product' => $this->product,
                'editTrail' => $this->editTrail,
            ],
        );
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/emails/product-edit-ready-for-acceptance.blade.php`:

```blade
<h1>Changes to your listing</h1>

<p>An administrator made changes to <strong>{{ $product->name }}</strong> while reviewing it. Please log in to the seller portal to review and accept these changes before the listing goes live.</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th align="left">Field</th>
            <th align="left">Previous Value</th>
            <th align="left">New Value</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($editTrail->changes as $field => $change)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                <td>{{ is_array($change['old']) ? implode(', ', $change['old']) : $change['old'] }}</td>
                <td>{{ is_array($change['new']) ? implode(', ', $change['new']) : $change['new'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p><a href="{{ route('filament.seller.auth.login') }}">Log in to review and accept</a></p>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ProductEditReadyForAcceptanceMailTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/ProductEditReadyForAcceptance.php \
        resources/views/emails/product-edit-ready-for-acceptance.blade.php \
        tests/Feature/ProductEditReadyForAcceptanceMailTest.php
git commit -m "Add ProductEditReadyForAcceptance mailable"
```

---

### Task 12: `ProductListingLive` mailable

**Files:**
- Create: `app/Mail/ProductListingLive.php`
- Create: `resources/views/emails/product-listing-live.blade.php`
- Test: `tests/Feature/ProductListingLiveMailTest.php`

**Interfaces:**
- Produces: `App\Mail\ProductListingLive` (constructed with `Product $product`), used by
  Task 13 (no-edits publish path) and Task 14 (seller acceptance path).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProductListingLiveMailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\ProductListingLive;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingLiveMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_links_to_the_live_product_page(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Aerial Fiber Cable', 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductListingLiveMailTest`
Expected: FAIL — the class doesn't exist.

- [ ] **Step 3: Write the mailable**

`app/Mail/ProductListingLive.php`:

```php
<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductListingLive extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your listing is now live: '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.product-listing-live',
            with: ['product' => $this->product],
        );
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/emails/product-listing-live.blade.php`:

```blade
<h1>Your listing is live</h1>

<p><strong>{{ $product->name }}</strong> is now published and visible to buyers on the catalog.</p>

<p><a href="{{ url('/products/'.$product->path()) }}">View your live listing</a></p>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ProductListingLiveMailTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/ProductListingLive.php \
        resources/views/emails/product-listing-live.blade.php \
        tests/Feature/ProductListingLiveMailTest.php
git commit -m "Add ProductListingLive mailable"
```

---

### Task 13: Admin edit-trail creation, `pending_seller_acceptance` status, publish-action gating and notification

**Files:**
- Modify: `app/Filament/Resources/ProductResource.php`
- Modify: `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
- Test: `tests/Feature/AdminProductEditTrailTest.php`

**Interfaces:**
- Consumes: `ProductEditReadyForAcceptance` (Task 11), `ProductListingLive` (Task 12),
  `Product::editTrails()` (Task 10).
- Produces: saving an Admin edit to a `pending_review` product whose tracked fields
  changed creates a `ProductEditTrail`, sets `status = 'pending_seller_acceptance'`, and
  emails the seller. The table's `publish` action becomes hidden while a product is
  `pending_seller_acceptance`, and sends `ProductListingLive` on a successful (no-edit)
  publish.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminProductEditTrailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Mail\ProductEditReadyForAcceptance;
use App\Mail\ProductListingLive;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductEditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_editing_a_tracked_field_on_a_pending_review_product_creates_a_trail_and_requires_seller_acceptance(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'short_description' => 'Original text',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['short_description' => 'Corrected text'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_seller_acceptance', $product->status);
        $this->assertSame('Corrected text', $product->short_description);

        $trail = $product->editTrails()->latest()->first();
        $this->assertNotNull($trail);
        $this->assertSame(['old' => 'Original text', 'new' => 'Corrected text'], $trail->changes['short_description']);
        $this->assertSame($admin->id, $trail->staff_id);

        Mail::assertSent(ProductEditReadyForAcceptance::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_saving_a_pending_review_product_with_no_tracked_field_changes_creates_no_trail(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'price_display' => null,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price_display' => '₹500 – ₹800'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame(0, $product->editTrails()->count());

        Mail::assertNotSent(ProductEditReadyForAcceptance::class);
    }

    public function test_the_publish_action_is_hidden_while_a_product_awaits_seller_acceptance(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('publish', $product);
    }

    public function test_approving_a_product_with_no_pending_changes_sends_the_live_notification(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'price_display' => '₹500 – ₹800',
        ]);

        Livewire::test(ListProducts::class)
            ->callTableAction('publish', $product);

        $this->assertSame('published', $product->fresh()->status);
        Mail::assertSent(ProductListingLive::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_editing_an_already_pending_seller_acceptance_product_does_not_error(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);

        // Reloading and re-saving a product already in this status must not fail
        // validation just because the currently-set status isn't a normally
        // selectable option -- mirrors the existing `published` handling.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['status' => 'pending_seller_acceptance'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending_seller_acceptance', $product->fresh()->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AdminProductEditTrailTest`
Expected: FAIL — `pending_seller_acceptance` doesn't exist as a status option, the
`publish` action has no status-based visibility check, and `EditProduct` has no
`mutateFormDataBeforeSave()`.

- [ ] **Step 3: Extend the status `Select` and gate/notify the `publish` action**

In `app/Filament/Resources/ProductResource.php`, add the imports:

```php
use App\Mail\ProductListingLive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
```

Replace the `Select::make('status')` block in `form()`:

```php
            Select::make('status')
                ->options(function (?Product $record) {
                    $options = [
                        'pending_review' => 'Pending Review',
                        'rejected' => 'Rejected',
                        'archived' => 'Archived',
                    ];

                    // A product that is already published, or awaiting seller
                    // acceptance of an Admin edit, keeps showing its current
                    // value when edited, but the option is disabled below --
                    // it can never be *chosen* from this select. Publishing
                    // only ever happens through the table's `publish` action
                    // (Product::publish()); pending_seller_acceptance is only
                    // ever set by EditProduct::mutateFormDataBeforeSave().
                    if ($record?->status === 'published') {
                        $options['published'] = 'Published';
                    }

                    if ($record?->status === 'pending_seller_acceptance') {
                        $options['pending_seller_acceptance'] = 'Pending Seller Acceptance';
                    }

                    return $options;
                })
                ->disableOptionWhen(fn (string $value) => in_array($value, ['published', 'pending_seller_acceptance']))
                ->in(function (?Product $record) {
                    $values = ['pending_review', 'rejected', 'archived'];

                    if ($record?->status === 'published') {
                        $values[] = 'published';
                    }

                    if ($record?->status === 'pending_seller_acceptance') {
                        $values[] = 'pending_seller_acceptance';
                    }

                    return $values;
                })
                ->default('pending_review')
                ->disabled(! $canSetPrice)
                ->dehydrated($canSetPrice)
                ->required(),
```

Replace the `publish` `Action` in `table()`:

```php
                Action::make('publish')
                    ->visible(fn (Product $record) => $record->status !== 'pending_seller_acceptance'
                        && (auth('staff')->user()?->can('approve', Product::class) ?? false))
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        if (! $record->publish()) {
                            return;
                        }

                        try {
                            Mail::to($record->seller->email)->send(new ProductListingLive($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send product listing live email.', [
                                'product_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
```

- [ ] **Step 4: Add the edit-trail creation hook**

`app/Filament/Resources/ProductResource/Pages/EditProduct.php`:

```php
<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Mail\ProductEditReadyForAcceptance;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    private const TRACKED_FIELDS = [
        'name', 'slug', 'sku', 'short_description', 'description',
        'features', 'applications', 'spec_sheet_path', 'category_id', 'quantity',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status !== 'pending_review') {
            return $data;
        }

        $changes = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $old = $this->record->getAttribute($field);
            $new = $data[$field] ?? null;

            if ($this->valuesDiffer($old, $new)) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === []) {
            return $data;
        }

        $trail = $this->record->editTrails()->create([
            'staff_id' => auth('staff')->id(),
            'changes' => $changes,
        ]);

        $data['status'] = 'pending_seller_acceptance';

        try {
            Mail::to($this->record->seller->email)->send(new ProductEditReadyForAcceptance($this->record, $trail));
        } catch (\Throwable $exception) {
            Log::error('Failed to send product edit acceptance email.', [
                'product_id' => $this->record->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $data;
    }

    private function valuesDiffer(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new)) {
            return json_encode(array_values((array) $old)) !== json_encode(array_values((array) $new));
        }

        return (string) $old !== (string) $new;
    }
}
```

(The `valuesDiffer()` normalization via `array_values()` guards against Filament's
Repeater re-keying an unchanged `features`/`applications` array internally without
actually changing its content or order.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AdminProductEditTrailTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — in particular, re-run
`php artisan test --filter=ProductResourceDehydrationSecurityTest` to confirm the
`published`-status handling this task mirrors is unaffected.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/ProductResource.php \
        app/Filament/Resources/ProductResource/Pages/EditProduct.php \
        tests/Feature/AdminProductEditTrailTest.php
git commit -m "Add admin edit-trail creation and gate publish on pending seller acceptance"
```

---

### Task 14: Seller "Accept Changes" action

**Files:**
- Modify: `app/Filament/Seller/Resources/ProductResource.php`
- Create: `resources/views/filament/seller/partials/edit-diff.blade.php`
- Test: `tests/Feature/SellerProductAcceptanceTest.php`

**Interfaces:**
- Consumes: `Product::latestPendingEditTrail()` (Task 10), `ProductListingLive`
  (Task 12).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SellerProductAcceptanceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\ListProducts;
use App\Mail\ProductListingLive;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_accept_pending_changes_and_the_product_goes_live(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);
        $trail = $product->editTrails()->create([
            'changes' => ['short_description' => ['old' => 'Old', 'new' => 'New']],
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->callTableAction('acceptChanges', $product);

        $product->refresh();
        $this->assertSame('published', $product->status);
        $this->assertNotNull($trail->fresh()->accepted_at);
        Mail::assertSent(ProductListingLive::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_accepting_changes_without_a_price_falls_back_to_pending_review(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_seller_acceptance',
            'price_display' => null,
        ]);
        $product->editTrails()->create(['changes' => ['short_description' => ['old' => 'Old', 'new' => 'New']]]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->callTableAction('acceptChanges', $product);

        $this->assertSame('pending_review', $product->fresh()->status);
        Mail::assertNotSent(ProductListingLive::class);
    }

    public function test_the_accept_changes_action_is_hidden_for_products_not_awaiting_acceptance(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'status' => 'pending_review']);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('acceptChanges', $product);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerProductAcceptanceTest`
Expected: FAIL — the seller `ProductResource` table has no `acceptChanges` action yet.

- [ ] **Step 3: Write the diff partial view**

`resources/views/filament/seller/partials/edit-diff.blade.php`:

```blade
<div class="space-y-2">
    @if ($trail)
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="text-left">Field</th>
                    <th class="text-left">Previous</th>
                    <th class="text-left">New</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trail->changes as $field => $change)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                        <td>{{ is_array($change['old']) ? implode(', ', $change['old']) : $change['old'] }}</td>
                        <td>{{ is_array($change['new']) ? implode(', ', $change['new']) : $change['new'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No pending changes found.</p>
    @endif
</div>
```

- [ ] **Step 4: Add the table action**

In `app/Filament/Seller/Resources/ProductResource.php`, add the imports:

```php
use App\Mail\ProductListingLive;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
```

Replace the `table()` method:

```php
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
            ])
            ->actions([
                Action::make('acceptChanges')
                    ->label('Accept Changes')
                    ->visible(fn (Product $record) => $record->status === 'pending_seller_acceptance')
                    ->requiresConfirmation()
                    ->modalHeading('Review Admin Changes')
                    ->modalContent(fn (Product $record) => view('filament.seller.partials.edit-diff', [
                        'trail' => $record->latestPendingEditTrail(),
                    ]))
                    ->modalSubmitActionLabel('Accept Changes')
                    ->action(function (Product $record) {
                        $trail = $record->latestPendingEditTrail();
                        $trail?->update(['accepted_at' => now()]);

                        if (! $record->publish()) {
                            $record->update(['status' => 'pending_review']);

                            return;
                        }

                        try {
                            Mail::to($record->seller->email)->send(new ProductListingLive($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send product listing live email.', [
                                'product_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
            ]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=SellerProductAcceptanceTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Seller/Resources/ProductResource.php \
        resources/views/filament/seller/partials/edit-diff.blade.php \
        tests/Feature/SellerProductAcceptanceTest.php
git commit -m "Add seller Accept Changes action for admin-edited pending listings"
```

---

### Task 15: Full verification pass

**Files:** none (verification only)

**Interfaces:** none — this task confirms Tasks 1-14 integrate correctly.

- [ ] **Step 1: Full reset and full test suite**

```bash
php artisan migrate:fresh --seed
php artisan test
```

Expected: no errors; all tests pass (baseline was 152 — expect roughly 152 + ~30 new
tests added across this plan; the exact count doesn't matter as long as everything is
green).

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: only files created/modified by Tasks 1-14 are listed.

- [ ] **Step 3: Manual smoke test**

```bash
php artisan serve
```

- Visit any published product page. Confirm the primary image (or the first image, if
  none is flagged) shows as the large hero slide, with other images as clickable
  thumbnails beneath it that jump the carousel to that slide.
- In `/admin`, open a product's Images relation manager, mark a second image
  "Primary." Confirm the first image's "Primary" toggle automatically clears.
- On a product page with related products, click a related-product card — confirm it
  navigates to that product's own page, and that the card shows a 132×132px thumbnail.
- Visit the homepage (or any page with a Featured Products block) — confirm thumbnails
  render at a fixed small size regardless of the number of products in the row.
- Submit a "Request a Quote" form for a product with an image. Check
  `storage/logs/laravel.log` for the RFQ notification email — confirm it includes a
  132×132px thumbnail and a "View Product Page" link.
- Visit `/seller/login` — confirm a "New seller? Register here" link appears and leads
  to `/seller/register`. Confirm it does **not** appear on `/admin/login`.
- Log into `/seller` with a seeded seller (`php artisan tinker` → `Seller::first()` to
  get current credentials, since there is no fixed seller login per `CLAUDE.md`).
  Confirm the dashboard loads without error (the crash this plan fixes).
- As a seller, create a product (status becomes `pending_review`). In `/admin`, open
  that product's edit page, change its short description, and save. Confirm the
  product's status becomes "Pending Seller Acceptance" and the log shows a
  `ProductEditReadyForAcceptance` email with a field-level diff. Confirm the `publish`
  table action is no longer visible for that product.
- Back in `/seller`, find that product (status "Pending Seller Acceptance"), click
  "Accept Changes," confirm the diff modal shows the change, submit it. Confirm the
  product becomes "Published" and the log shows a `ProductListingLive` email.
- Create a second seller product, open it in `/admin` and directly click "Publish"
  without editing anything first (ensure `price_display` is set). Confirm it publishes
  immediately and the log shows a `ProductListingLive` email — the no-edits path.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "Product images, related products, seller login, and edit-acceptance plan complete: verified end-to-end"
```
