<?php

namespace Tests\Feature;

use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_nav_items_render_with_their_children_as_a_dropdown(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $parent = NavItem::factory()->create(['label' => 'Company', 'url' => '#', 'location' => 'header', 'sort_order' => 1]);
        NavItem::factory()->create(['label' => 'About Us', 'url' => '/about', 'location' => 'header', 'parent_id' => $parent->id, 'sort_order' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Company');
        $response->assertSee('About Us');
    }

    public function test_footer_nav_items_render(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        NavItem::factory()->create(['label' => 'Privacy Policy', 'url' => '/privacy', 'location' => 'footer', 'sort_order' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Privacy Policy');
    }

    public function test_seeded_home_and_contact_us_pages_are_reachable(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/contact-us')->assertOk();
    }
}
