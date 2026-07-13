<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestNote;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_request_can_belong_to_a_product(): void
    {
        $product = Product::factory()->create();
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $this->assertTrue($quoteRequest->product->is($product));
    }

    public function test_a_quote_request_can_be_a_general_inquiry_with_no_product(): void
    {
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => null]);

        $this->assertNull($quoteRequest->product);
    }

    public function test_a_quote_request_can_have_notes_from_staff(): void
    {
        $quoteRequest = QuoteRequest::factory()->create();
        $staff = Staff::factory()->create();

        $note = QuoteRequestNote::create([
            'quote_request_id' => $quoteRequest->id,
            'staff_id' => $staff->id,
            'note' => 'Called the buyer, following up next week.',
        ]);

        $this->assertTrue($quoteRequest->notes->contains($note));
        $this->assertTrue($note->staff->is($staff));
    }
}
