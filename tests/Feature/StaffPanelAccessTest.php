<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_staff_member_with_the_admin_role_can_access_the_admin_panel(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');

        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_staff_member_without_any_role_cannot_access_the_admin_panel(): void
    {
        $staff = Staff::factory()->create();

        $this->assertFalse($staff->canAccessPanel(Filament::getPanel('admin')));
    }
}
