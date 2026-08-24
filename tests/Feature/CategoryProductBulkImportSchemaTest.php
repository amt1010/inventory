<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductBulkImportSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_created_with_no_seller(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['seller_id' => null, 'category_id' => $category->id]);

        $this->assertNull($product->fresh()->seller_id);
    }

    public function test_material_type_is_not_nullable_and_the_factory_supplies_one(): void
    {
        $product = Product::factory()->create();

        $this->assertContains($product->fresh()->material_type, ['raw_material', 'finished_good']);
    }

    public function test_products_and_categories_created_by_default_to_null(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();

        $this->assertNull($product->fresh()->created_by);
        $this->assertNull($category->fresh()->created_by);
    }

    public function test_the_category_placeholder_constant_is_the_literal_string_to_be_added(): void
    {
        $this->assertSame('TO BE ADDED', Category::PLACEHOLDER);
    }
}
