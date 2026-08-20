<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_manage_roles_regardless_of_the_permission_matrix(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $role = Role::findByName('sales', 'staff');

        $this->assertTrue($admin->can('viewAny', Role::class));
        $this->assertTrue($admin->can('create', Role::class));
        $this->assertTrue($admin->can('update', $role));
        $this->assertTrue($admin->can('delete', $role));

        $this->assertFalse($editor->can('viewAny', Role::class));
        $this->assertFalse($editor->can('create', Role::class));
        $this->assertFalse($editor->can('update', $role));
        $this->assertFalse($editor->can('delete', $role));
    }
}
