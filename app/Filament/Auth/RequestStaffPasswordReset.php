<?php

namespace App\Filament\Auth;

use App\Mail\StaffPasswordReset;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class RequestStaffPasswordReset extends RequestPasswordReset
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

        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $email],
            function (CanResetPassword $user, string $token): void {
                if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentPanel()))) {
                    return;
                }

                Mail::to($user->email)->send(new StaffPasswordReset(
                    $user,
                    Filament::getResetPasswordUrl($token, $user),
                ));
            },
        );

        // Always the same response, whether the account exists or not --
        // withholds the enumeration signal (spec: "Sellers").
        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();
        $this->form->fill();
    }
}
