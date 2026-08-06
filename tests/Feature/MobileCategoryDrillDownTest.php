<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCategoryDrillDownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_the_mobile_products_trigger_opens_the_root_panel(): void
    {
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-open="root"', false);
        $response->assertSee('data-mcn-panel="root"', false);
    }

    public function test_a_category_with_children_gets_a_drill_in_chevron(): void
    {
        $hub = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        Category::factory()->create(['name' => 'Aerial', 'parent_id' => $hub->id, 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-open="cat-'.$hub->id.'"', false);
    }

    public function test_a_leaf_category_has_no_drill_in_chevron(): void
    {
        $leaf = Category::factory()->create(['name' => 'Standalone Category', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-mcn-open="cat-'.$leaf->id.'"', false);
    }

    public function test_a_third_level_category_is_reachable(): void
    {
        $top = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        $sub = Category::factory()->create(['name' => 'Aerial', 'parent_id' => $top->id, 'status' => 'published']);
        $leaf = Category::factory()->create(['name' => 'ADSS', 'parent_id' => $sub->id, 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-mcn-panel="cat-'.$sub->id.'"', false);
        $response->assertSee('ADSS');
        $response->assertSee('href="'.url('/products/'.$top->slug.'/'.$sub->slug.'/'.$leaf->slug).'"', false);
    }

    public function test_a_draft_category_never_appears_in_the_drill_down(): void
    {
        Category::factory()->create(['name' => 'Hidden Draft Category', 'status' => 'draft']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Hidden Draft Category');
    }

    public function test_a_category_name_links_straight_to_its_page(): void
    {
        $category = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.url('/products/'.$category->slug).'"', false);
    }
}
