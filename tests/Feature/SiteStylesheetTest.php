<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteStylesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_layout_links_the_custom_stylesheet(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('css/site.css', escape: false);
    }

    public function test_the_stylesheet_defines_contrast_fixes_for_carousel_arrows_and_footer_links(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        // Product carousel arrows get a dark backing so they are visible on the
        // light letterbox background (issue #13).
        $this->assertStringContainsString('#productImagesCarousel .carousel-control-prev-icon', $css);
        // Footer links use a high-contrast colour rather than the low-contrast grey.
        $this->assertStringContainsString('#f1f3f5', $css);
    }

    public function test_the_stylesheet_pins_the_footer_to_the_bottom_on_short_pages(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        // The body is a full-height flex column so the main content can grow and
        // push the footer to the bottom instead of floating up mid-page.
        $this->assertStringContainsString('min-height: 100vh', $css);
        $this->assertStringContainsString('flex: 1 0 auto', $css);
    }
}
