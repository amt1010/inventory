<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerRejected;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerRejectedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_shows_the_reason_section_when_one_is_set(): void
    {
        EmailTemplate::forKey('seller_rejected')->update([
            'body' => '<p>{{company_name}} rejected.</p>{{#rejection_reason}}<p>Reason: {{rejection_reason}}</p>{{/rejection_reason}}',
        ]);

        $seller = Seller::factory()->create(['company_name' => 'Acme Co', 'rejection_reason' => 'Documents did not match.']);

        $mailable = new SellerRejected($seller);

        $mailable->assertSeeInHtml('Acme Co rejected.');
        $mailable->assertSeeInHtml('Reason: Documents did not match.');
    }

    public function test_drops_the_reason_section_when_none_is_set(): void
    {
        EmailTemplate::forKey('seller_rejected')->update([
            'body' => '<p>Before</p>{{#rejection_reason}}<p>Reason</p>{{/rejection_reason}}<p>After</p>',
        ]);

        $seller = Seller::factory()->create(['rejection_reason' => null]);

        $mailable = new SellerRejected($seller);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }
}
