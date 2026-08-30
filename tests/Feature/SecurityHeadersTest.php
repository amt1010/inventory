<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_the_baseline_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_the_content_security_policy_allows_the_cdn_assets_the_layout_actually_loads(): void
    {
        $response = $this->get(route('login'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('fonts.googleapis.com', $csp);
        $this->assertStringContainsString('fonts.gstatic.com', $csp);
    }

    public function test_the_content_security_policy_allows_the_recaptcha_script_and_frame(): void
    {
        $response = $this->get(route('login'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('www.google.com/recaptcha/', $csp);
        $this->assertStringContainsString('www.gstatic.com/recaptcha/', $csp);
        $this->assertStringContainsString('frame-src', $csp);
    }

    public function test_the_content_security_policy_allows_filaments_default_avatar_provider(): void
    {
        $response = $this->get(route('login'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('img-src', $csp);
        $this->assertStringContainsString('https://ui-avatars.com', $csp);
    }

    public function test_hsts_is_sent_on_secure_requests_but_not_on_plain_http(): void
    {
        $secure = $this->get('https://surpluskart.test/login');
        $secure->assertHeader('Strict-Transport-Security');

        $plain = $this->get('http://surpluskart.test/login');
        $plain->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_the_content_security_policy_has_no_clerk_host_when_clerk_is_unconfigured(): void
    {
        config([
            'services.clerk.publishable_key' => null,
            'services.clerk.frontend_api' => null,
        ]);

        $response = $this->get(route('register'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('clerk', $csp);
    }

    public function test_the_content_security_policy_allows_the_configured_clerk_host(): void
    {
        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);

        $response = $this->get(route('register'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression(
            "/script-src[^;]*https:\/\/test\.clerk\.accounts\.dev/",
            $csp
        );
        $this->assertMatchesRegularExpression(
            "/connect-src[^;]*https:\/\/test\.clerk\.accounts\.dev/",
            $csp
        );
    }
}
