<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerActivationMail;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerActivationMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_an_admin_created_seller_uses_the_admin_created_template(): void
    {
        EmailTemplate::forKey('seller_activation_admin_created')->update([
            'body' => '<p>Admin-created copy for {{company_name}}: {{activation_url}}</p>',
        ]);

        $seller = Seller::factory()->create(['created_by' => 'admin', 'company_name' => 'Acme Co']);

        $mailable = new SellerActivationMail($seller);

        $mailable->assertSeeInHtml('Admin-created copy for Acme Co');
    }

    public function test_a_self_registered_seller_uses_the_self_registered_template(): void
    {
        EmailTemplate::forKey('seller_activation_self_registered')->update([
            'body' => '<p>Self-registered copy for {{company_name}}: {{activation_url}}</p>',
        ]);

        $seller = Seller::factory()->create(['created_by' => 'self', 'company_name' => 'Acme Co']);

        $mailable = new SellerActivationMail($seller);

        $mailable->assertSeeInHtml('Self-registered copy for Acme Co');
    }
}
