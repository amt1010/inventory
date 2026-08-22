<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_is_hidden_on_every_form_when_recaptcha_is_not_configured(): void
    {
        config(['services.recaptcha.site_key' => null]);

        $this->get('/login')->assertDontSee('g-recaptcha', false);
        $this->get('/register')->assertDontSee('g-recaptcha', false);
        $this->get('/seller/register')->assertDontSee('g-recaptcha', false);
    }

    public function test_the_widget_shows_on_every_form_when_recaptcha_is_configured(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key']);

        $this->get('/login')->assertSee('g-recaptcha', false);
        $this->get('/register')->assertSee('g-recaptcha', false);
        $this->get('/seller/register')->assertSee('g-recaptcha', false);

        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [['type' => 'newsletter_signup', 'data' => []]],
        ]);
        $this->get('/')->assertSee('g-recaptcha', false);
    }

    public function test_the_recaptcha_script_only_loads_when_configured(): void
    {
        config(['services.recaptcha.site_key' => null]);
        $this->get('/login')->assertDontSee('recaptcha/api.js', false);

        config(['services.recaptcha.site_key' => 'test-site-key']);
        $this->get('/login')->assertSee('recaptcha/api.js', false);
    }

    public function test_buyer_login_is_rejected_when_recaptcha_is_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false])]);
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'g-recaptcha-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest('web');
    }

    public function test_buyer_registration_is_rejected_when_recaptcha_is_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false])]);

        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_accepted' => '1',
            'g-recaptcha-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_seller_registration_is_rejected_when_recaptcha_is_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false])]);

        $response = $this->post('/seller/register', [
            'company_name' => 'Rao Traders',
            'contact_person' => 'Asha Rao',
            'phone' => '9876543210',
            'email' => 'asha@example.com',
            'business_address' => '123 Market Road',
            'gst_number' => '27AAAAA0000A1Z5',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_accepted' => '1',
            'g-recaptcha-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_newsletter_subscription_is_rejected_when_recaptcha_is_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false])]);

        $response = $this->post('/newsletter/subscribe', [
            'email' => 'buyer@example.com',
            'g-recaptcha-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('subscribers', 0);
    }
}
