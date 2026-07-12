<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sales_can_view_categories_but_cannot_create_update_or_delete(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $category = Category::factory()->create();

        $this->assertTrue($sales->can('viewAny', Category::class));
        $this->assertTrue($sales->can('view', $category));
        $this->assertFalse($sales->can('create', Category::class));
        $this->assertFalse($sales->can('update', $category));
        $this->assertFalse($sales->can('delete', $category));
    }

    public function test_admin_and_content_editor_can_create_update_and_delete_categories(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $category = Category::factory()->create();

        foreach ([$admin, $editor] as $staff) {
            $this->assertTrue($staff->can('create', Category::class));
            $this->assertTrue($staff->can('update', $category));
            $this->assertTrue($staff->can('delete', $category));
        }
    }
}
