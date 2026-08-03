<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_layout_renders_the_cookie_consent_banner(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookie-consent-banner"', false);
        $response->assertSee('id="cookie-consent-accept"', false);
    }

    public function test_the_footer_has_a_cookie_settings_link_instead_of_the_old_placeholder(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookie-settings-link"', false);
        $response->assertDontSee('data-cookies-placeholder', false);
    }
}
