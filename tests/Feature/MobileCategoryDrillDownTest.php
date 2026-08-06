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

        // The mobile drill-down only renders when some header NavItem opts
        // into the category menu — same flag the desktop mega-menu already
        // requires. Without this, every test below would fail after the
        // fix that made rendering conditional (previously unconditional
        // rendering leaked every category name into every page's header,
        // regardless of nav configuration — see PageBlockRenderingTest).
        NavItem::factory()->create([
            'label' => 'Products', 'url' => '/products', 'location' => 'header',
            'parent_id' => null, 'show_category_menu' => true,
        ]);
    }

    public function test_the_mobile_products_trigger_opens_the_root_panel(): void
    {
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

    public function test_the_mobile_nav_script_is_linked_and_defines_the_panel_switching_hooks(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('js/mobile-category-nav.js', false);

        $js = file_get_contents(public_path('js/mobile-category-nav.js'));
        $this->assertStringContainsString('data-mcn-open', $js);
        $this->assertStringContainsString('data-mcn-back', $js);
    }
}
