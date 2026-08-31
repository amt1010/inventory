<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetLinkRequest;
use App\Mail\BuyerPasswordReset;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Eligibility is "has a local password", not "has no Clerk link" --
        // a buyer can have both (spec: "Eligibility rule"). Checked here
        // rather than relying on Password::sendResetLink's own lookup,
        // since that has no concept of "found but not eligible".
        if ($user && $user->password !== null) {
            Password::broker('users')->sendResetLink(
                ['email' => $user->email],
                function (CanResetPassword $resetUser, string $token): void {
                    Mail::to($resetUser->email)->send(new BuyerPasswordReset(
                        $resetUser,
                        url('/reset-password/'.$token.'?email='.urlencode($resetUser->email)),
                    ));
                },
            );
        }

        // Always the same response, whether the account doesn't exist, is
        // Clerk-only, or is a real password-based match -- withholds the
        // enumeration signal (spec: "Buyers").
        return redirect()->route('login')->with('status', 'If that email is registered, we\'ve sent a password reset link.');
    }

    public function showResetForm(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }
}
