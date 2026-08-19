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

        $labels = ['Dashboard', 'Site Settings', 'Nav Items', 'Pages', 'Categories', 'Products', 'Quote Requests', 'Sellers'];
        $positions = array_map(fn (string $label) => strpos($response->getContent(), $label), $labels);

        $this->assertNotContains(false, $positions, 'Every expected nav label should appear in the sidebar.');
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }
}
