<?php

namespace Tests\Feature\Mail;

use App\Mail\ProductListingLive;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Product;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingLiveTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_subject_and_body(): void
    {
        EmailTemplate::forKey('product_listing_live')->update([
            'subject' => 'It is live: {{product_name}}',
            'body' => '<p>Custom copy for {{product_name}} at {{product_url}}</p>',
        ]);

        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Aerial Fiber Cable', 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertHasSubject('It is live: Aerial Fiber Cable');
        $mailable->assertSeeInHtml('Custom copy for Aerial Fiber Cable');
    }

    public function test_the_email_applies_the_templates_cc_and_bcc(): void
    {
        EmailTemplate::forKey('product_listing_live')->update([
            'default_cc' => 'ops@example.com',
            'default_bcc' => 'audit@example.com',
        ]);

        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertHasCc('ops@example.com');
        $mailable->assertHasBcc('audit@example.com');
    }
}
