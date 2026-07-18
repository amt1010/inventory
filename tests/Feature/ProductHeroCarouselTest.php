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

    public function test_the_main_image_uses_best_fit_and_is_not_cropped(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/one.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('object-fit: contain', false);
        $response->assertDontSee('object-fit: cover;', false);
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
