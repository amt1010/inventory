<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSignupBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_heading_subheading_and_posts_to_the_subscribe_route(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'newsletter_signup', 'data' => [
                    'heading' => 'Get sourcing updates & deals',
                    'subheading' => 'One email a month, no spam.',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Get sourcing updates & deals');
        $response->assertSee('One email a month, no spam.');
        $response->assertSee('action="'.route('newsletter.subscribe').'"', escape: false);
    }
}
