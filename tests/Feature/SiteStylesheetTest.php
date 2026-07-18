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
}
