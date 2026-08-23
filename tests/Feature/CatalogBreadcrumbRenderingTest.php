<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogBreadcrumbRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_link_points_to_the_site_root_not_the_products_page(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'electronics']);

        $response = $this->get('/products/electronics');

        $response->assertOk();
        $response->assertSee('href="'.url('/').'"', false);
        $response->assertDontSee('href="'.url('/products').'">Home', false);
    }

    public function test_a_products_breadcrumb_node_is_present_and_links_to_the_catalog_root(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'electronics']);

        $response = $this->get('/products/electronics');

        $response->assertOk();
        $response->assertSee('href="'.url('/products').'">Products', false);
    }

    public function test_the_full_category_chain_renders_as_visible_breadcrumb_links(): void
    {
        $parent = Category::factory()->create(['status' => 'published', 'slug' => 'electronics', 'name' => 'ELECTRONICS']);
        $child = Category::factory()->create([
            'status' => 'published', 'slug' => 'electronics-items', 'name' => 'Electronics Items', 'parent_id' => $parent->id,
        ]);

        $response = $this->get('/products/electronics/electronics-items');

        $response->assertOk();
        $response->assertSee('ELECTRONICS');
        $response->assertSee('Electronics Items');
        $response->assertSee('href="'.url('/products/electronics').'"', false);
        $response->assertSee('href="'.url('/products/electronics/electronics-items').'"', false);
    }

    public function test_a_category_with_show_in_breadcrumb_false_is_omitted_but_its_slug_still_resolves_deeper_links(): void
    {
        $parent = Category::factory()->create([
            'status' => 'published', 'slug' => 'electronics', 'name' => 'ELECTRONICS', 'show_in_breadcrumb' => false,
        ]);
        $child = Category::factory()->create([
            'status' => 'published', 'slug' => 'electronics-items', 'name' => 'Electronics Items', 'parent_id' => $parent->id,
        ]);

        $response = $this->get('/products/electronics/electronics-items');

        $response->assertOk();
        $response->assertDontSee('ELECTRONICS');
        $response->assertSee('Electronics Items');
        // The visible child's link must still include the hidden parent's
        // slug in its path -- hiding a crumb's label must not break the URL.
        $response->assertSee('href="'.url('/products/electronics/electronics-items').'"', false);
    }

    public function test_the_product_page_breadcrumb_has_the_same_home_and_products_fixes(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'audio']);
        $product = Product::factory()->create([
            'category_id' => $category->id, 'status' => 'published', 'slug' => 'audio-speakers', 'name' => 'AUDIO SPEAKERS',
        ]);

        $response = $this->get('/products/audio/audio-speakers');

        $response->assertOk();
        $response->assertSee('href="'.url('/').'"', false);
        $response->assertSee('href="'.url('/products').'">Products', false);
        $response->assertSee('AUDIO SPEAKERS');
    }

    public function test_a_hidden_category_on_a_product_page_is_omitted_but_the_products_link_still_works(): void
    {
        $category = Category::factory()->create([
            'status' => 'published', 'slug' => 'audio', 'name' => 'Audio Category', 'show_in_breadcrumb' => false,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id, 'status' => 'published', 'slug' => 'audio-speakers', 'name' => 'AUDIO SPEAKERS',
        ]);

        $response = $this->get('/products/audio/audio-speakers');

        $response->assertOk();
        $response->assertDontSee('Audio Category');
        $response->assertSee('AUDIO SPEAKERS');
    }
}
