<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\QuoteRequest;
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

    public function test_quantity_is_fillable_and_nullable(): void
    {
        $product = Product::factory()->create(['quantity' => 500]);

        $this->assertSame(500, $product->fresh()->quantity);

        $productWithoutQuantity = Product::factory()->create(['quantity' => null]);

        $this->assertNull($productWithoutQuantity->fresh()->quantity);
    }

    public function test_quote_requests_relation_returns_related_quote_requests(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        $match = QuoteRequest::factory()->create(['product_id' => $product->id]);
        QuoteRequest::factory()->create(['product_id' => $other->id]);

        $this->assertCount(1, $product->quoteRequests);
        $this->assertTrue($product->quoteRequests->first()->is($match));
    }

    public function test_status_after_edit_reverts_published_to_pending_review(): void
    {
        $product = Product::factory()->create(['status' => 'published']);

        $this->assertSame('pending_review', $product->statusAfterEdit());
    }

    public function test_status_after_edit_leaves_non_published_status_unchanged(): void
    {
        $product = Product::factory()->create(['status' => 'rejected']);

        $this->assertSame('rejected', $product->statusAfterEdit());
    }

    public function test_a_product_cannot_be_published_while_its_category_is_still_draft(): void
    {
        $category = Category::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price_display' => '₹1,000 – ₹1,500',
            'status' => 'pending_review',
        ]);

        $result = $product->publish();

        $this->assertFalse($result);
        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_publish_blockers_reports_a_missing_price(): void
    {
        $product = Product::factory()->create(['price_display' => null, 'status' => 'pending_review']);

        $blockers = $product->publishBlockers();

        $this->assertCount(1, $blockers);
        $this->assertStringContainsString('price', strtolower($blockers[0]));
    }

    public function test_publish_blockers_reports_an_unpublished_category(): void
    {
        $category = Category::factory()->create(['status' => 'draft', 'name' => 'Gadgets']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price_display' => '₹1,000 – ₹1,500',
            'status' => 'pending_review',
        ]);

        $blockers = $product->publishBlockers();

        $this->assertCount(1, $blockers);
        $this->assertStringContainsString('category', strtolower($blockers[0]));
        $this->assertStringContainsString('Gadgets', $blockers[0]);
    }

    public function test_publish_blockers_lists_every_missing_detail_at_once(): void
    {
        $category = Category::factory()->create(['status' => 'draft', 'name' => 'Gadgets']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price_display' => null,
            'status' => 'pending_review',
        ]);

        $blockers = $product->publishBlockers();

        // Both problems are surfaced together, not one-at-a-time.
        $this->assertCount(2, $blockers);
        $this->assertStringContainsString('price', strtolower(implode(' ', $blockers)));
        $this->assertStringContainsString('category', strtolower(implode(' ', $blockers)));
    }

    public function test_publish_blockers_is_empty_when_the_product_can_be_published(): void
    {
        $product = Product::factory()->create(['price_display' => '₹1,000 – ₹1,500', 'status' => 'pending_review']);

        $this->assertSame([], $product->publishBlockers());
    }
}
