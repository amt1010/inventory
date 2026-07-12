<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_products_hub_lists_top_level_published_categories(): void
    {
        $topLevel = Category::factory()->create(['status' => 'published', 'slug' => 'fiber-optic-cable']);
        Category::factory()->create(['status' => 'draft', 'slug' => 'hidden-category']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->contains($topLevel) && $children->count() === 1);
    }

    public function test_a_nested_category_path_resolves_and_builds_a_breadcrumb(): void
    {
        $parent = Category::factory()->create(['status' => 'published', 'slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['status' => 'published', 'slug' => 'aerial', 'parent_id' => $parent->id]);

        $response = $this->get('/products/fiber-optic-cable/aerial');

        $response->assertOk();
        $response->assertViewHas('category', fn ($category) => $category->is($child));
        $response->assertViewHas('breadcrumb', fn ($breadcrumb) => array_map(fn ($c) => $c->id, $breadcrumb) === [$parent->id, $child->id]);
    }

    public function test_a_product_slug_as_the_final_segment_renders_the_product_page(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'aerial']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'slug' => 'centracore-opgw-cable',
        ]);

        $response = $this->get('/products/aerial/centracore-opgw-cable');

        $response->assertOk();
        $response->assertViewIs('catalog.product');
        $response->assertViewHas('product', fn ($p) => $p->is($product));
    }

    public function test_a_pending_review_product_is_not_reachable_publicly(): void
    {
        $category = Category::factory()->create(['status' => 'published', 'slug' => 'aerial']);
        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'pending_review',
            'slug' => 'unapproved-cable',
        ]);

        $response = $this->get('/products/aerial/unapproved-cable');

        $response->assertNotFound();
    }

    public function test_an_unknown_path_returns_404(): void
    {
        $response = $this->get('/products/does-not-exist');

        $response->assertNotFound();
    }
}
