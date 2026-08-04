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
