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
