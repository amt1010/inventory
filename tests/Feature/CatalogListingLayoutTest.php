<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogListingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_total_product_count_and_a_sort_dropdown(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->count(3)->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('3 products found');
        $response->assertSee('name="sort"', escape: false);
    }

    public function test_it_renders_a_checkbox_for_each_filter_value(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('Color');
        $response->assertSee('Red');
        $response->assertSee('name="attr[Color][]"', escape: false);
    }

    public function test_a_checked_filter_checkbox_reflects_the_active_query_param(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $response->assertSee('checked', escape: false);
    }

    public function test_a_category_with_no_filter_groups_shows_no_empty_filter_panel(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertDontSee('<h6 class="mb-3">Filters</h6>', false);
    }
}
