<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_matching_published_products_and_categories_by_name(): void
    {
        Product::factory()->create(['name' => 'Wireless Router', 'status' => 'published']);
        Category::factory()->create(['name' => 'Wireless Accessories', 'status' => 'published']);
        Product::factory()->create(['name' => 'Copper Cable', 'status' => 'published']);

        $response = $this->getJson('/search/suggest?q=wire');

        $response->assertOk();
        $labels = collect($response->json())->pluck('label');
        $this->assertTrue($labels->contains('Wireless Router'));
        $this->assertTrue($labels->contains('Wireless Accessories'));
        $this->assertFalse($labels->contains('Copper Cable'));
    }

    public function test_suggest_excludes_non_published_products_and_categories(): void
    {
        Product::factory()->create(['name' => 'Wireless Hidden Product', 'status' => 'pending_review']);
        Category::factory()->create(['name' => 'Wireless Draft Category', 'status' => 'draft']);

        $response = $this->getJson('/search/suggest?q=wire');

        $response->assertOk();
        $labels = collect($response->json())->pluck('label');
        $this->assertFalse($labels->contains('Wireless Hidden Product'));
        $this->assertFalse($labels->contains('Wireless Draft Category'));
    }

    public function test_suggest_returns_empty_for_a_query_shorter_than_two_characters(): void
    {
        Product::factory()->create(['name' => 'Wireless Router', 'status' => 'published']);

        $response = $this->getJson('/search/suggest?q=w');

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_suggest_returns_empty_for_a_blank_query(): void
    {
        $response = $this->getJson('/search/suggest?q=');

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_suggest_caps_the_number_of_results(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Product::factory()->create(['name' => "Wireless Item {$i}", 'status' => 'published']);
        }

        $response = $this->getJson('/search/suggest?q=wireless');

        $response->assertOk();
        $this->assertLessThanOrEqual(8, count($response->json()));
    }

    public function test_suggest_result_includes_a_navigable_url(): void
    {
        $product = Product::factory()->create(['name' => 'Wireless Router', 'status' => 'published']);

        $response = $this->getJson('/search/suggest?q=wire');

        $response->assertOk();
        $match = collect($response->json())->firstWhere('label', 'Wireless Router');
        $this->assertNotNull($match);
        $this->assertSame('/products/'.$product->path(), $match['url']);
    }
}
