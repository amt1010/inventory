<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_email_creates_a_subscriber_and_shows_a_flash_message(): void
    {
        $response = $this->post('/newsletter/subscribe', ['email' => 'buyer@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('newsletter_subscribed');
        $this->assertDatabaseHas('subscribers', ['email' => 'buyer@example.com']);
    }

    public function test_resubmitting_the_same_email_does_not_create_a_duplicate_row(): void
    {
        Subscriber::query()->create(['email' => 'buyer@example.com']);

        $response = $this->post('/newsletter/subscribe', ['email' => 'buyer@example.com']);

        $response->assertRedirect();
        $this->assertDatabaseCount('subscribers', 1);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $response = $this->post('/newsletter/subscribe', ['email' => 'not-an-email']);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('subscribers', 0);
    }
}
