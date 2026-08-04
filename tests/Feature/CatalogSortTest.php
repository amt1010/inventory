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
