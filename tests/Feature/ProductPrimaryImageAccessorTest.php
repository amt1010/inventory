<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPrimaryImageAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_flagged_primary_image(): void
    {
        $product = Product::factory()->create();
        $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $primary = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->assertTrue($product->primaryImage()->is($primary));
    }

    public function test_it_falls_back_to_the_first_image_by_sort_order_when_none_is_flagged(): void
    {
        $product = Product::factory()->create();
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => false]);

        $this->assertTrue($product->primaryImage()->is($first));
    }

    public function test_it_returns_null_when_the_product_has_no_images(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->primaryImage());
    }
}
