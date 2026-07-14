<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_a_nav_item_with_the_mega_menu_flag_shows_the_live_category_tree(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);
        $root = Category::factory()->create(['parent_id' => null, 'name' => 'Fiber Optic Cable', 'slug' => 'fiber-optic-cable', 'status' => 'published']);
        Category::factory()->create(['parent_id' => $root->id, 'name' => 'Aerial', 'slug' => 'aerial', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Fiber Optic Cable');
        $response->assertSee('Aerial');
        $response->assertSee('/products/fiber-optic-cable/aerial', escape: false);
    }

    public function test_a_draft_category_never_appears_in_the_mega_menu(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);
        Category::factory()->create(['parent_id' => null, 'name' => 'Hidden Draft Category', 'status' => 'draft']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Hidden Draft Category');
    }

    public function test_a_nav_item_without_the_flag_keeps_its_own_manual_children(): void
    {
        $parent = NavItem::factory()->create(['label' => 'Company', 'url' => '/company', 'location' => 'header', 'parent_id' => null, 'show_category_menu' => false]);
        NavItem::factory()->create(['label' => 'About Us', 'url' => '/about', 'location' => 'header', 'parent_id' => $parent->id]);
        Category::factory()->create(['parent_id' => null, 'name' => 'Should Not Appear Here', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('About Us');
    }
}
