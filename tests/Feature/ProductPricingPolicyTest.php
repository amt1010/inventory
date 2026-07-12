<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_the_admin_role_can_set_product_price(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($admin->can('setPrice', Product::class));
        $this->assertFalse($editor->can('setPrice', Product::class));
    }

    public function test_only_the_admin_role_can_approve_a_product(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertTrue($admin->can('approve', Product::class));
        $this->assertFalse($sales->can('approve', Product::class));
    }
}
