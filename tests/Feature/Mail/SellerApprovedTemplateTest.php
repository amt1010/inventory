<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerApproved;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerApprovedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_shows_the_activation_link_section_when_one_is_given(): void
    {
        EmailTemplate::forKey('seller_approved')->update([
            'body' => '<p>{{company_name}} approved.</p>{{#activation_url}}<p><a href="{{activation_url}}">Set Password</a></p>{{/activation_url}}',
        ]);

        $seller = Seller::factory()->create(['company_name' => 'Acme Co']);

        $mailable = new SellerApproved($seller, 'https://example.test/activate/1');

        $mailable->assertSeeInHtml('Acme Co approved.');
        $mailable->assertSeeInHtml('https://example.test/activate/1', escape: false);
    }

    public function test_drops_the_activation_link_section_when_none_is_given(): void
    {
        EmailTemplate::forKey('seller_approved')->update([
            'body' => '<p>Before</p>{{#activation_url}}<p>Set password link</p>{{/activation_url}}<p>After</p>',
        ]);

        $seller = Seller::factory()->create();

        $mailable = new SellerApproved($seller, null);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }
}
