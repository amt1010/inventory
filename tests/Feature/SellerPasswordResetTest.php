<?php

namespace Tests\Feature;

use App\Mail\SellerPasswordReset;
use App\Models\Seller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class SellerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Unlike the admin panel, the seller panel isn't ->default(), so
        // Filament::getCurrentPanel() has nothing to fall back to when
        // Livewire::test() instantiates the page directly without going
        // through panel routing/middleware -- set it explicitly.
        Filament::setCurrentPanel(Filament::getPanel('seller'));

        // Mail::assertQueued(..., fn ($mail) => $mail->hasTo(...)) calls
        // envelope() directly (Mailable::hasRecipient() -> hasEnvelopeRecipient())
        // even under Mail::fake(), so SellerPasswordReset::envelope()'s
        // EmailTemplate::forKey('seller_password_reset') lookup needs a real
        // row -- same reason every other Mail-template test in this suite
        // (e.g. StaffPasswordResetTest) seeds this in setUp().
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);
    }

    public function test_a_password_based_seller_can_request_and_complete_a_reset(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['company_name' => 'Acme Co']);

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => $seller->email])
            ->call('request');

        Mail::assertQueued(SellerPasswordReset::class, fn ($mail) => $mail->hasTo($seller->email));

        $token = Password::broker('sellers')->createToken($seller);

        // Filament's ResetPassword page is a Livewire component reached via
        // panel routing, not a plain POST endpoint -- same pattern as the
        // staff test. The page class itself is unmodified (Filament's
        // default), reused here.
        Livewire::test(\Filament\Pages\Auth\PasswordReset\ResetPassword::class, [
            'email' => $seller->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('resetPassword');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $seller->fresh()->password));
    }

    public function test_a_clerk_only_seller_gets_the_same_response_but_no_email_is_sent(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['password' => null, 'clerk_user_id' => 'user_clerk123']);

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => $seller->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_email_gets_the_same_response_and_no_email_is_sent(): void
    {
        Mail::fake();

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }
}
