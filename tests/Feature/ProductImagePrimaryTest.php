<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImagePrimaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_image_primary_unsets_primary_on_sibling_images(): void
    {
        $product = Product::factory()->create();
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);

        $second->update(['is_primary' => true]);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_marking_an_image_primary_does_not_affect_another_products_images(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $otherImage = $otherProduct->images()->create(['path' => 'product-images/other.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $product->images()->create(['path' => 'product-images/own.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $this->assertTrue($otherImage->fresh()->is_primary);
    }

    public function test_creating_a_second_primary_image_directly_also_unsets_the_first(): void
    {
        $product = Product::factory()->create();
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->assertFalse($first->fresh()->is_primary);
    }
}
