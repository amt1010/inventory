<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sellers_landing_page_offers_registration_and_login(): void
    {
        $response = $this->get('/sellers');

        $response->assertOk();
        $response->assertSee(route('seller.register'), escape: false);
        $response->assertSee(route('filament.seller.auth.login'), escape: false);
    }

    public function test_the_header_login_as_seller_link_points_to_the_sellers_landing_page(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $home = $this->get('/');

        $home->assertOk();
        $home->assertSee(url('/sellers'), escape: false);
    }
}
