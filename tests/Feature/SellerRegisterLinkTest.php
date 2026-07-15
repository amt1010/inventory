<?php

namespace Tests\Feature;

use Tests\TestCase;

class SellerRegisterLinkTest extends TestCase
{
    public function test_the_seller_login_page_links_to_self_registration(): void
    {
        $response = $this->get(route('filament.seller.auth.login'));

        $response->assertOk();
        $response->assertSee(route('seller.register'), false);
    }

    public function test_the_admin_login_page_does_not_show_the_seller_registration_link(): void
    {
        $response = $this->get(route('filament.admin.auth.login'));

        $response->assertOk();
        $response->assertDontSee(route('seller.register'), false);
    }
}
