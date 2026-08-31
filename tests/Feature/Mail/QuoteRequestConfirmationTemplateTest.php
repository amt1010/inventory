<?php

namespace Tests\Feature\Mail;

use App\Mail\QuoteRequestConfirmation;
use App\Models\EmailTemplate;
use App\Models\QuoteRequest;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestConfirmationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_and_shows_the_product_section_when_present(): void
    {
        EmailTemplate::forKey('quote_request_confirmation')->update([
            'subject' => 'Ref {{quote_number}} received',
            'body' => '<p>Hi {{first_name}}, ref {{quote_number}}.</p>{{#product_name}}<p>About {{product_name}}</p>{{/product_name}}',
        ]);

        $product = \App\Models\Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $quoteRequest = QuoteRequest::factory()->create([
            'first_name' => 'Asha',
            'quote_number' => 'QR-1001',
            'product_id' => $product->id,
        ]);

        $mailable = new QuoteRequestConfirmation($quoteRequest);

        $mailable->assertHasSubject('Ref QR-1001 received');
        $mailable->assertSeeInHtml('Hi Asha, ref QR-1001.');
        $mailable->assertSeeInHtml('About Aerial Fiber Cable');
    }

    public function test_the_product_section_is_dropped_when_there_is_no_product(): void
    {
        EmailTemplate::forKey('quote_request_confirmation')->update([
            'body' => '<p>Before</p>{{#product_name}}<p>About {{product_name}}</p>{{/product_name}}<p>After</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create(['product_id' => null]);

        $mailable = new QuoteRequestConfirmation($quoteRequest);

        $mailable->assertDontSeeInHtml('About');
    }
}
