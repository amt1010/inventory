<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_published_products_by_name(): void
    {
        Product::factory()->create(['name' => 'CentraCore OPGW Cable', 'status' => 'published']);
        Product::factory()->create(['name' => 'Unrelated Widget', 'status' => 'published']);

        $response = $this->get('/search?q=OPGW');

        $response->assertOk();
        $response->assertSee('CentraCore OPGW Cable');
        $response->assertDontSee('Unrelated Widget');
    }

    public function test_search_excludes_non_published_products(): void
    {
        Product::factory()->create(['name' => 'Hidden Cable', 'status' => 'pending_review']);

        $response = $this->get('/search?q=Hidden');

        $response->assertOk();
        $response->assertDontSee('Hidden Cable');
    }
}
