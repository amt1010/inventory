<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notification_email_renders_the_quote_requests_details(): void
    {
        $quoteRequest = QuoteRequest::factory()->create([
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'email' => 'asha@example.com',
            'message' => 'Need pricing for 500 meters.',
        ]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('Asha Rao');
        $mailable->assertSeeInHtml('asha@example.com');
        $mailable->assertSeeInHtml('Need pricing for 500 meters.');
    }

    public function test_the_notification_email_includes_the_products_thumbnail_and_a_link_to_its_page(): void
    {
        $category = \App\Models\Category::factory()->create(['status' => 'published']);
        $product = \App\Models\Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/quote-thumb.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('quote-thumb.jpg');
        $mailable->assertSeeInHtml('width="132"');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }
}
