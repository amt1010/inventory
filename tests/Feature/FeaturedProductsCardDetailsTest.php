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
        $response->assertDontSee($product->seller->company_name);
    }

    public function test_the_product_thumbnail_is_not_grayscaled(): void
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
        $response->assertDontSee('md-grayscale', false);
    }
}
