<?php

namespace Tests\Feature\Seller;

use Tests\TestCase;

class ClerkPanelLoginButtonVisibilityTest extends TestCase
{
    public function test_the_button_appears_on_the_seller_panel_login_page_when_clerk_is_configured(): void
    {
        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);

        $this->get('/seller/login')->assertSee('clerk-google-seller-login', false);
    }

    public function test_the_button_is_hidden_when_clerk_is_not_configured(): void
    {
        config(['services.clerk.publishable_key' => null]);

        $this->get('/seller/login')->assertDontSee('clerk-google-seller-login', false);
    }
}
