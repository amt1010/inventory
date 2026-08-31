<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_KEYS = [
        'product_listing_live',
        'quote_request_confirmation',
        'quote_request_received',
        'seller_activation_admin_created',
        'seller_activation_self_registered',
        'seller_approved',
        'seller_rejected',
        'staff_invitation',
        'staff_password_reset',
        'seller_password_reset',
        'buyer_password_reset',
    ];

    public function test_seeds_exactly_the_eight_expected_system_templates(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $this->assertSame(
            self::EXPECTED_KEYS,
            EmailTemplate::query()->orderBy('id')->pluck('key')->all()
        );
    }

    public function test_every_seeded_template_is_system_and_starts_unmodified(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (EmailTemplate::all() as $template) {
            $this->assertTrue($template->is_system, "{$template->key} should be is_system");
            $this->assertFalse($template->isModified(), "{$template->key} should start with draft == live");
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $this->assertSame(11, EmailTemplate::count());
    }

    public function test_seller_activation_templates_carry_the_two_distinct_variants(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $admin = EmailTemplate::forKey('seller_activation_admin_created');
        $self = EmailTemplate::forKey('seller_activation_self_registered');

        $this->assertStringContainsString('An administrator has created', $admin->body);
        $this->assertStringContainsString('Thanks for registering', $self->body);
    }

    public function test_the_three_password_reset_templates_contain_the_reset_url_token(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (['staff_password_reset', 'seller_password_reset', 'buyer_password_reset'] as $key) {
            $this->assertStringContainsString('{{reset_url}}', EmailTemplate::forKey($key)->body);
        }
    }
}
