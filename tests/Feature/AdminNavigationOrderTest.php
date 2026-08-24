<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sidebar_lists_nav_items_in_the_configured_order(): void
    {
        $this->seed(RoleSeeder::class);

        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();

        // Scoped to the sidebar item labels specifically (not a whole-page
        // text search) so dashboard widget headings that happen to share a
        // word with a nav label (e.g. "Categories by Status") can't shift
        // the result.
        preg_match_all('/fi-sidebar-item-label[^>]*>\s*(.+?)\s*</s', $response->getContent(), $matches);

        $this->assertSame(
            ['Dashboard', 'Site Settings', 'Nav Items', 'Pages', 'Categories', 'Products', 'Quote Requests', 'Sellers', 'Roles', 'Staff', 'Audit Logs'],
            $matches[1],
        );
    }
}
