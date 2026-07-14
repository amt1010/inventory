<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroCarouselBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_carousel_renders_only_active_slides(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [
                    ['media_type' => 'image', 'heading' => 'Visible Slide', 'active' => true],
                    ['media_type' => 'image', 'heading' => 'Hidden Slide', 'active' => false],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Visible Slide');
        $response->assertDontSee('Hidden Slide');
    }

    public function test_a_video_slide_renders_a_video_element(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [
                    ['media_type' => 'video', 'video_url' => 'https://example.com/promo.mp4', 'heading' => 'Watch This', 'active' => true],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://example.com/promo.mp4', escape: false);
    }

    public function test_two_carousels_on_one_page_get_unique_dom_ids(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [['media_type' => 'image', 'heading' => 'First', 'active' => true]]]],
                ['type' => 'hero_carousel', 'data' => ['slides' => [['media_type' => 'image', 'heading' => 'Second', 'active' => true]]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="heroCarousel0"', escape: false);
        $response->assertSee('id="heroCarousel1"', escape: false);
    }
}
