<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_manage_staff_logins_regardless_of_the_permission_matrix(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $target = Staff::factory()->create();

        $this->assertTrue($admin->can('viewAny', Staff::class));
        $this->assertTrue($admin->can('create', Staff::class));
        $this->assertTrue($admin->can('update', $target));
        $this->assertTrue($admin->can('delete', $target));

        $this->assertFalse($editor->can('viewAny', Staff::class));
        $this->assertFalse($editor->can('create', Staff::class));
        $this->assertFalse($editor->can('update', $target));
        $this->assertFalse($editor->can('delete', $target));
    }
}
