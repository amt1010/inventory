<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkButtonVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);
    }

    public function test_the_button_appears_on_buyer_register(): void
    {
        $this->get('/register')->assertSee('clerk-google-btn-buyer', false);
    }

    public function test_the_button_appears_on_buyer_login(): void
    {
        $this->get('/login')->assertSee('clerk-google-btn-buyer', false);
    }

    public function test_the_button_appears_on_the_seller_landing_page(): void
    {
        $this->get('/sellers')->assertSee('clerk-google-btn-seller_register', false);
    }

    public function test_the_button_appears_on_seller_register_when_not_already_clerk_identified(): void
    {
        $this->get('/seller/register')->assertSee('clerk-google-btn-seller_register', false);
    }

    public function test_the_button_is_hidden_on_seller_register_once_a_clerk_identity_is_in_session(): void
    {
        $this->withSession(['seller_clerk_identity' => [
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ]]);

        $this->get('/seller/register')->assertDontSee('clerk-google-btn-seller_register', false);
    }
}
