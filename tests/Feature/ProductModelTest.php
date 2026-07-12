<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_belongs_to_a_seller_and_a_category(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($product->seller->is($seller));
        $this->assertTrue($product->category->is($category));
    }

    public function test_a_product_cannot_be_published_without_a_price(): void
    {
        $product = Product::factory()->create(['price_display' => null, 'status' => 'pending_review']);

        $result = $product->publish();

        $this->assertFalse($result);
        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_a_product_with_a_price_can_be_published(): void
    {
        $product = Product::factory()->create(['price_display' => '₹1,000 – ₹1,500', 'status' => 'pending_review']);

        $result = $product->publish();

        $this->assertTrue($result);
        $this->assertSame('published', $product->fresh()->status);
    }
}
