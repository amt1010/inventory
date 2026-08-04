<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsBannerBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_heading_body_and_cta(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'deals_banner', 'data' => [
                    'heading' => 'Bulk Deals This Week',
                    'body' => 'Save on high-volume orders across select categories.',
                    'cta_label' => 'Shop Deals',
                    'cta_url' => '/products',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Bulk Deals This Week');
        $response->assertSee('Save on high-volume orders across select categories.');
        $response->assertSee('Shop Deals');
        $response->assertSee('href="/products"', escape: false);
    }
}
