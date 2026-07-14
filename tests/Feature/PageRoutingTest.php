<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_published_page_with_slug_home(): void
    {
        Page::factory()->create(['slug' => 'home', 'title' => 'Welcome Home', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Welcome Home');
    }

    public function test_missing_homepage_404s(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }

    public function test_a_published_page_resolves_by_slug(): void
    {
        Page::factory()->create(['slug' => 'about', 'title' => 'About Us', 'status' => 'published']);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('About Us');
    }

    public function test_a_draft_page_404s_on_the_public_site(): void
    {
        Page::factory()->create(['slug' => 'about', 'status' => 'draft']);

        $response = $this->get('/about');

        $response->assertNotFound();
    }

    public function test_search_still_resolves_ahead_of_the_catch_all_slug_route(): void
    {
        $response = $this->get('/search?q=cable');

        $response->assertOk();
    }
}
