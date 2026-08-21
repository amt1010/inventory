<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResizableSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_panel_ships_the_sidebar_resize_handle(): void
    {
        $this->seed(RoleSeeder::class);

        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('fi-sidebar-resize-handle', escape: false);
        $response->assertSee('filament-sidebar-width', escape: false);
    }

    public function test_the_seller_panel_ships_the_sidebar_resize_handle(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $response = $this->get('/seller');

        $response->assertOk();
        $response->assertSee('fi-sidebar-resize-handle', escape: false);
        $response->assertSee('filament-sidebar-width', escape: false);
    }

    /**
     * Both panel providers boot on every request and both register an unscoped
     * HEAD_END hook, so without the `getCurrentPanel()` guard the partial --
     * and its `window.filamentSidebarResizeInitialised` script -- would be
     * emitted twice on every page.
     */
    public function test_the_resize_handle_is_only_injected_once_per_page(): void
    {
        $this->seed(RoleSeeder::class);

        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        $content = $this->get('/admin')->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($content, 'window.filamentSidebarResizeInitialised = true'),
        );
    }
}
