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

    public function test_the_stylesheet_prevents_the_header_search_box_overflowing_on_mobile(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        // A flex item's default min-width is `auto`, which lets the search
        // input's long placeholder push it (and the form) wider than the
        // viewport on mobile instead of shrinking. min-width: 0 overrides that.
        $this->assertStringContainsString('.site-search-form', $css);
        $this->assertStringContainsString('min-width: 0', $css);
    }

    public function test_the_header_search_form_has_the_overflow_fix_class(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('site-search-form', false);
    }

    public function test_the_stylesheet_defines_the_mobile_category_panel_visibility_rules(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.mcn-panel', $css);
        $this->assertStringContainsString('.mcn-panel.is-active', $css);
        $this->assertStringContainsString('.mcn-hidden', $css);
    }

    public function test_the_stylesheet_hides_the_desktop_mega_menu_below_the_lg_breakpoint(): void
    {
        $css = file_get_contents(public_path('css/site.css'));

        // Scoped inside a max-width media query rather than a d-lg-block
        // utility class on the element itself, so it never competes with
        // Bootstrap's own (non-!important) .dropdown-menu.show rule at
        // lg+ — see MegaMenuTest for the bug this caused when it did.
        $this->assertStringContainsString('@media (max-width: 991.98px)', $css);
        $this->assertMatchesRegularExpression('/\.mega-menu\s*\{\s*display:\s*none\s*!important;/', $css);
    }
}
