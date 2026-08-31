<?php

namespace Tests\Feature\Mail;

use App\Mail\QuoteRequestReceived;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\QuoteRequest;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestReceivedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_subject_and_body(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'subject' => 'New enquiry from {{full_name}}',
            'body' => '<p>{{reason}} from {{full_name}} ({{email}}, {{phone}}, {{company}})</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create([
            'reason' => 'General Inquiry',
            'first_name' => 'Asha', 'last_name' => 'Rao',
            'email' => 'asha@example.com', 'phone' => '9999999999', 'company' => 'Acme',
        ]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertHasSubject('New enquiry from Asha Rao');
        $mailable->assertSeeInHtml('General Inquiry from Asha Rao (asha@example.com, 9999999999, Acme)');
    }

    public function test_the_product_section_includes_the_thumbnail_and_link_when_a_product_is_set(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'body' => '{{#product_name}}<p>{{product_name}}</p>{{product_thumbnail_html}}<p><a href="{{product_url}}">View</a></p>{{/product_name}}',
        ]);

        $product = Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $product->images()->create(['path' => 'product-images/test-thumbnail.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml('width="132"');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }

    public function test_the_message_section_is_dropped_when_there_is_no_message(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'body' => '<p>Before</p>{{#message_text}}<p>{{message_text}}</p>{{/message_text}}<p>After</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create(['message' => null]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }

    public function test_the_subject_does_not_html_escape_apostrophes_or_ampersands(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'subject' => 'New Quote Request from {{full_name}}',
        ]);

        $quoteRequest = QuoteRequest::factory()->create([
            'first_name' => "O'Brien", 'last_name' => '& Sons',
        ]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertHasSubject("New Quote Request from O'Brien & Sons");
    }
}
