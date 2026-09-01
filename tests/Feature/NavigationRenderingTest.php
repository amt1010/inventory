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

    public function test_footer_renders_columns_with_their_child_links(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        $resources = NavItem::factory()->create(['label' => 'Resources', 'url' => '#', 'location' => 'footer', 'sort_order' => 1]);
        NavItem::factory()->create(['label' => 'Documentation', 'url' => '/docs', 'location' => 'footer', 'parent_id' => $resources->id, 'sort_order' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Resources');
        $response->assertSee('Documentation');
        $response->assertSee('href="/docs"', false);
    }

    public function test_the_footer_products_column_lists_top_level_published_categories_instead_of_authored_children(): void
    {
        Page::factory()->create(['slug' => 'home', 'status' => 'published']);

        NavItem::factory()->create(['label' => 'Products', 'url' => '#', 'location' => 'footer', 'sort_order' => 1]);
        $category = \App\Models\Category::factory()->create(['name' => 'Fibre Optic Cables', 'status' => 'published', 'parent_id' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Fibre Optic Cables');
        $response->assertSee('href="'.url('/products/'.$category->fresh()->path()).'"', false);
    }

    public function test_seeded_home_and_contact_us_pages_are_reachable(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/contact-us')->assertOk();
    }
}
