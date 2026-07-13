<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_view_sellers(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertTrue($admin->can('viewAny', Seller::class));
        $this->assertFalse($editor->can('viewAny', Seller::class));
        $this->assertFalse($sales->can('viewAny', Seller::class));
    }

    public function test_only_admin_can_manage_a_specific_seller(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $seller = Seller::factory()->create();

        $this->assertTrue($admin->can('update', $seller));
        $this->assertFalse($editor->can('update', $seller));
    }
}
