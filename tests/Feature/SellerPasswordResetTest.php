<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SellerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $seller = Seller::factory()->create(['status' => 'approved']);

        $status = Password::broker('sellers')->sendResetLink(['email' => $seller->email]);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
        Notification::assertSentTo($seller, ResetPassword::class);
    }

    public function test_a_seller_can_reset_their_password_with_a_valid_token(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);

        $token = Password::broker('sellers')->createToken($seller);

        $status = Password::broker('sellers')->reset(
            ['email' => $seller->email, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'token' => $token],
            function ($seller, $password) {
                $seller->forceFill(['password' => bcrypt($password)])->save();
            }
        );

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $seller->fresh()->password));
    }

    public function test_the_seller_password_reset_broker_is_scoped_to_sellers_not_users(): void
    {
        $this->assertSame('sellers', config('auth.passwords.sellers.provider'));
        $this->assertSame('seller_password_reset_tokens', config('auth.passwords.sellers.table'));
    }

    public function test_the_forgot_password_page_is_reachable(): void
    {
        $this->get('/seller/password-reset/request')->assertOk();
    }
}
