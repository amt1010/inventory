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
}
