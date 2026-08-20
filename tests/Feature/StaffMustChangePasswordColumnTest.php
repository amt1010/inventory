<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMustChangePasswordColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_must_change_password_defaults_to_false_and_is_mass_assignable(): void
    {
        $staff = Staff::factory()->create();

        $this->assertFalse($staff->fresh()->must_change_password);

        $staff2 = Staff::factory()->create(['must_change_password' => true]);

        $this->assertTrue($staff2->fresh()->must_change_password);
    }
}
