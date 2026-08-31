<?php

namespace App\Filament\Seller\Auth;

use App\Mail\SellerPasswordReset;
use App\Models\Seller;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class RequestSellerPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $email = $this->form->getState()['email'];
        $seller = Seller::where('email', $email)->first();

        // Eligibility is "has a local password", not "has no Clerk link" --
        // checked here rather than relying on Password::sendResetLink's own
        // user lookup, since that has no concept of "found but not
        // eligible" (spec: "Eligibility rule").
        if ($seller && $seller->password !== null) {
            Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                ['email' => $email],
                function (CanResetPassword $user, string $token): void {
                    Mail::to($user->email)->send(new SellerPasswordReset(
                        $user,
                        Filament::getResetPasswordUrl($token, $user),
                    ));
                },
            );
        }

        // Always the same response, whether the account doesn't exist, is
        // Clerk-only, or is a real password-based match -- withholds the
        // enumeration signal (spec: "Sellers").
        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();
        $this->form->fill();
    }
}
