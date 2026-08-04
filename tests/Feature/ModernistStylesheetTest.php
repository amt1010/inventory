<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModernistStylesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_layout_links_the_modernist_stylesheet(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('css/modernist.css', escape: false);
    }

    public function test_the_stylesheet_defines_the_modernist_design_tokens(): void
    {
        $css = file_get_contents(public_path('css/modernist.css'));

        $this->assertStringContainsString('--color-accent: #ff6a00', $css);
        $this->assertStringContainsString('--color-bg: #f3f2f2', $css);
        $this->assertStringContainsString('--font-heading', $css);
    }
}
