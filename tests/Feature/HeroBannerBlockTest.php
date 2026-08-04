<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroBannerBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_banner_renders_its_copy_and_both_ctas(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_banner', 'data' => [
                    'tag' => 'B2B Sourcing Marketplace',
                    'heading' => 'Sourcing Cable & Wire — and Everything Else — Simplified',
                    'body' => 'Browse thousands of verified listings and request a quote in minutes.',
                    'search_placeholder' => 'Search for item by keyword or product number',
                    'cta_primary_label' => 'Browse Products',
                    'cta_primary_url' => '/products',
                    'cta_secondary_label' => 'Request a Quote',
                    'cta_secondary_url' => '/#rfq',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('B2B Sourcing Marketplace');
        $response->assertSee('Sourcing Cable & Wire'); // assertSee escapes this for us — matches Blade's auto-escaped output
        $response->assertSee('Browse Products');
        $response->assertSee('Request a Quote');
        $response->assertSee('/products', escape: false);
        $response->assertSee('/#rfq', escape: false);
    }

    public function test_the_hero_search_form_submits_to_the_real_catalog_search_route(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_banner', 'data' => ['heading' => 'Test Heading', 'search_placeholder' => 'Search']],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('action="'.route('catalog.search').'"', escape: false);
        $response->assertSee('name="q"', escape: false);
    }
}
