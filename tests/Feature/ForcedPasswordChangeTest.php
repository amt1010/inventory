<?php

namespace Tests\Feature;

use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_staff_member_who_must_change_password_is_redirected_from_any_admin_route(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin');

        $response->assertRedirect(route('admin.change-password'));
    }

    public function test_a_staff_member_who_must_change_password_can_reach_the_change_password_route_itself(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get(route('admin.change-password'));

        $response->assertOk();
    }

    public function test_submitting_a_valid_new_password_clears_the_flag_and_allows_normal_access(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => true]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->post(route('admin.change-password.update'), [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertFalse($staff->fresh()->must_change_password);

        $this->actingAs($staff->fresh(), 'staff')->get('/admin')->assertOk();
    }

    public function test_a_staff_member_with_the_flag_already_false_is_never_redirected(): void
    {
        $staff = Staff::factory()->create(['must_change_password' => false]);
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin');

        $response->assertOk();
    }
}
