<?php

namespace Tests\Feature;

use App\Mail\StaffPasswordReset;
use App\Models\Staff;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire::test() instantiates the page directly, bypassing the
        // panel routing/middleware that normally sets "current panel" --
        // without this, Filament::getAuthPasswordBroker() (used inside
        // RequestStaffPasswordReset::request()) has no panel to read from.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Mail::assertQueued(..., fn ($mail) => $mail->hasTo(...)) calls
        // envelope() directly (Mailable::hasRecipient() -> hasEnvelopeRecipient())
        // even under Mail::fake(), so StaffPasswordReset::envelope()'s
        // EmailTemplate::forKey('staff_password_reset') lookup needs a real
        // row -- same reason every other Mail-template test in this suite
        // (e.g. StaffInvitationTemplateTest) seeds this in setUp().
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);
    }

    public function test_a_staff_member_can_request_and_complete_a_self_service_reset(): void
    {
        Mail::fake();

        $staff = Staff::factory()->create(['must_change_password' => true]);

        Livewire::test(\App\Filament\Auth\RequestStaffPasswordReset::class)
            ->fillForm(['email' => $staff->email])
            ->call('request');

        Mail::assertQueued(StaffPasswordReset::class, fn ($mail) => $mail->hasTo($staff->email));

        $token = Password::broker('staff')->createToken($staff);

        // Filament's ResetPassword page is a Livewire component reached via
        // panel routing, not a plain POST endpoint -- test it the same way
        // as the request page above, not with a raw HTTP request. The page
        // class itself is unmodified (Filament's default), reused here.
        Livewire::test(\Filament\Pages\Auth\PasswordReset\ResetPassword::class, [
            'email' => $staff->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('resetPassword');

        $staff->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $staff->password));
        $this->assertFalse($staff->must_change_password);
    }

    public function test_requesting_a_reset_for_an_unknown_email_gives_the_same_response_as_a_known_one(): void
    {
        Mail::fake();

        Livewire::test(\App\Filament\Auth\RequestStaffPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }

    public function test_admin_triggered_reset_still_sets_must_change_password_true(): void
    {
        // EditStaff's resetPassword action queues StaffInvitation, which
        // renders via EmailTemplate::forKey('staff_invitation') -- with
        // QUEUE_CONNECTION=sync that render happens inline unless faked, and
        // this test doesn't seed EmailTemplateSeeder. Matches the same
        // Mail::fake() used in StaffResourceTest's equivalent test; this
        // test is about must_change_password, not mail delivery.
        Mail::fake();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staffMember = Staff::factory()->create(['must_change_password' => false]);

        Livewire::test(\App\Filament\Resources\StaffResource\Pages\EditStaff::class, ['record' => $staffMember->id])
            ->callAction('resetPassword');

        $this->assertTrue($staffMember->fresh()->must_change_password);
    }
}
