<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\CategoryProductRowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductRowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blank_product_name_returns_null(): void
    {
        $result = (new CategoryProductRowResolver())->resolveProduct([
            'product_name' => '',
            'parent_name' => 'Plastic',
        ]);

        $this->assertNull($result);
    }

    public function test_a_named_product_resolves_to_an_unsaved_record_with_category_and_slug_set(): void
    {
        $result = (new CategoryProductRowResolver())->resolveProduct([
            'product_name' => 'PC DANA-BLACK',
            'parent_name' => 'Plastic',
            'sub1_name' => 'Plastic Granules',
        ]);

        $this->assertNotNull($result);
        $this->assertFalse($result->exists);
        $this->assertSame('PC DANA-BLACK', $result->name);
        $this->assertSame('pc-dana-black', $result->slug);
        $this->assertSame('Plastic Granules', $result->category->name);
    }

    public function test_a_product_already_existing_in_the_resolved_category_returns_null(): void
    {
        $category = (new \App\Services\CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'sub1_name' => 'Plastic Granules',
        ]);
        Product::factory()->create(['name' => 'PC DANA-BLACK', 'category_id' => $category->id]);

        $result = (new CategoryProductRowResolver())->resolveProduct([
            'product_name' => 'PC DANA-BLACK',
            'parent_name' => 'Plastic',
            'sub1_name' => 'Plastic Granules',
        ]);

        $this->assertNull($result);
    }

    public function test_product_slugs_are_deduplicated_within_the_same_category(): void
    {
        $category = (new \App\Services\CategoryChainResolver())->resolve(['parent_name' => 'Plastic']);
        Product::factory()->create(['name' => 'Existing Widget', 'slug' => 'widget', 'category_id' => $category->id]);

        $result = (new CategoryProductRowResolver())->resolveProduct([
            'product_name' => 'Widget',
            'parent_name' => 'Plastic',
        ]);

        $this->assertSame('widget-2', $result->slug);
    }
}
