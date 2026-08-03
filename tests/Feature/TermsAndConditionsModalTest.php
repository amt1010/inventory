<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAndConditionsModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_buyer_registration_page_shows_the_terms_content(): void
    {
        Page::factory()->create([
            'slug' => 'terms-and-conditions',
            'status' => 'published',
            'title' => 'Terms & Conditions',
            'content' => [
                ['type' => 'content_strip', 'data' => ['heading' => 'Terms & Conditions', 'body' => '<p>Test terms body.</p>']],
            ],
        ]);

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Test terms body.', false);
    }

    public function test_the_seller_registration_page_shows_the_terms_content(): void
    {
        Page::factory()->create([
            'slug' => 'terms-and-conditions',
            'status' => 'published',
            'title' => 'Terms & Conditions',
            'content' => [
                ['type' => 'content_strip', 'data' => ['heading' => 'Terms & Conditions', 'body' => '<p>Seller terms body.</p>']],
            ],
        ]);

        $response = $this->get('/seller/register');

        $response->assertOk();
        $response->assertSee('Seller terms body.', false);
    }
}
