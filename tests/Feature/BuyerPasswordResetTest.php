<?php

namespace Tests\Feature;

use App\Mail\BuyerPasswordReset;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class BuyerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_a_password_based_buyer_can_request_and_complete_a_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Asha', 'password' => Hash::make('old-password')]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertQueued(BuyerPasswordReset::class, fn ($mail) => $mail->hasTo($user->email));

        $token = Password::broker('users')->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_a_clerk_only_buyer_gets_a_redirect_but_no_email_is_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'clerk_user_id' => 'user_clerk123']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_email_gets_the_same_redirect_and_no_email_is_sent(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_the_forgot_password_route_is_rate_limited(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertStatus(429);
    }
}
