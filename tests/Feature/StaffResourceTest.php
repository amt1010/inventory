<?php

namespace Tests\Feature;

use App\Filament\Resources\StaffResource\Pages\CreateStaff;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class StaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_view_the_staff_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'staff')->get('/admin/staff');

        $response->assertOk();
    }

    public function test_a_content_editor_gets_a_403_visiting_the_staff_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $response = $this->actingAs($editor, 'staff')->get('/admin/staff');

        $response->assertForbidden();
    }

    public function test_creating_a_staff_login_hashes_a_temp_password_flags_forced_change_assigns_roles_and_queues_the_invitation(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'new-editor@example.test',
                'roles' => ['content_editor'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = Staff::where('email', 'new-editor@example.test')->firstOrFail();

        $this->assertTrue($staff->must_change_password);
        $this->assertTrue($staff->hasRole('content_editor'));
        $this->assertMatchesRegularExpression('/^\$2y\$/', $staff->password);

        Mail::assertQueued(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->staff->is($staff));
    }

    public function test_resetting_a_password_reflags_forced_change_and_resends_the_invitation(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staff = Staff::factory()->create(['must_change_password' => false]);
        $staff->assignRole('sales');
        $originalPassword = $staff->password;

        Livewire::test(EditStaff::class, ['record' => $staff->id])
            ->callAction('resetPassword');

        $staff->refresh();

        $this->assertTrue($staff->must_change_password);
        $this->assertNotSame($originalPassword, $staff->password);

        Mail::assertQueued(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->staff->is($staff));
    }
}
