<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAttributeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_controller_computes_one_filter_group_per_distinct_attribute_label(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertViewHas('filterGroups', function ($groups) {
            return $groups->has('Color') && $groups['Color']->sort()->values()->all() === ['Blue', 'Red'];
        });
    }

    public function test_a_category_with_no_custom_attributes_has_no_filter_groups(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertViewHas('filterGroups', fn ($groups) => $groups->isEmpty());
    }

    public function test_selecting_a_value_narrows_results_to_matching_products_only(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$red->id], $ids);
    }

    public function test_selecting_two_values_in_the_same_group_matches_either(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $red = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $red->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $blue->customAttributes()->create(['label' => 'Color', 'value' => 'Blue']);
        $green = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $green->customAttributes()->create(['label' => 'Color', 'value' => 'Green']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red&attr[Color][]=Blue');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$red->id, $blue->id], $ids);
    }

    public function test_selecting_values_in_two_different_groups_requires_a_product_to_match_both(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $matches = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $matches->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $matches->customAttributes()->create(['label' => 'Material', 'value' => 'Copper']);

        $colorOnly = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $colorOnly->customAttributes()->create(['label' => 'Color', 'value' => 'Red']);
        $colorOnly->customAttributes()->create(['label' => 'Material', 'value' => 'Aluminum']);

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red&attr[Material][]=Copper');

        $response->assertOk();
        $ids = $response->viewData('products')->pluck('id')->all();
        $this->assertSame([$matches->id], $ids);
    }

    public function test_the_next_page_url_preserves_active_filter_params(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $products = Product::factory()->count(12)->create(['category_id' => $category->id, 'status' => 'published']);
        $products->each(fn ($p) => $p->customAttributes()->create(['label' => 'Color', 'value' => 'Red']));

        $response = $this->get('/products/'.$category->slug.'?attr[Color][]=Red');

        $response->assertOk();
        $nextPageUrl = urldecode($response->viewData('products')->nextPageUrl());
        $this->assertStringContainsString('attr', $nextPageUrl);
        $this->assertStringContainsString('Red', $nextPageUrl);
    }
}
