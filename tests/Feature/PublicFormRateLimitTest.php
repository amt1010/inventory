<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_request_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(route('quote-requests.store'), []);
        }

        $response->assertStatus(429);
    }

    public function test_newsletter_subscription_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(route('newsletter.subscribe'), []);
        }

        $response->assertStatus(429);
    }

    public function test_seller_registration_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(route('seller.register.store'), []);
        }

        $response->assertStatus(429);
    }

    public function test_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $response = $this->get(route('catalog.search', ['q' => 'test']));
        }

        $response->assertStatus(429);
    }

    public function test_search_suggest_is_rate_limited(): void
    {
        for ($i = 0; $i < 65; $i++) {
            $response = $this->get(route('catalog.search.suggest', ['q' => 'test']));
        }

        $response->assertStatus(429);
    }
}
