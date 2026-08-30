<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkCompletionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_completion_page_loads(): void
    {
        $response = $this->get('/auth/clerk/complete?intent=buyer');

        $response->assertOk();
        $response->assertSee('Signing you in', false);
    }

    public function test_the_layout_always_includes_a_csrf_meta_tag(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
    }

    public function test_clerk_js_is_only_loaded_when_configured(): void
    {
        config(['services.clerk.publishable_key' => null]);
        $this->get('/register')->assertDontSee('clerk-js', false);

        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);
        $this->get('/register')->assertSee('data-clerk-publishable-key', false);
    }
}
