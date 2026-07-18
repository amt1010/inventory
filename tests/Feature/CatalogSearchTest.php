<?php

namespace Tests\Feature;

use App\Models\Category;
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

    public function test_search_returns_matching_published_categories(): void
    {
        Category::factory()->create(['name' => 'Fibre Optic Cables', 'status' => 'published']);
        Category::factory()->create(['name' => 'Copper Widgets', 'status' => 'published']);

        $response = $this->get('/search?q=Fibre');

        $response->assertOk();
        $response->assertSee('Fibre Optic Cables');
        $response->assertDontSee('Copper Widgets');
    }

    public function test_search_returns_matching_sub_categories(): void
    {
        $parent = Category::factory()->create(['name' => 'Cables', 'status' => 'published']);
        Category::factory()->create([
            'name' => 'Armoured Distribution',
            'parent_id' => $parent->id,
            'status' => 'published',
        ]);

        $response = $this->get('/search?q=Armoured');

        $response->assertOk();
        $response->assertSee('Armoured Distribution');
    }

    public function test_search_excludes_draft_categories(): void
    {
        Category::factory()->create(['name' => 'Secret Draft Category', 'status' => 'draft']);

        $response = $this->get('/search?q=Secret');

        $response->assertOk();
        $response->assertDontSee('Secret Draft Category');
    }
}
