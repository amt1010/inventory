<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (blank($secret)) {
            // reCAPTCHA is not configured for this environment — skip verification
            // rather than blocking every submission until keys are provisioned.
            return;
        }

        if (blank($value)) {
            $fail('Please confirm you are not a robot.');

            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $value,
        ]);

        if (! $response->json('success', false)) {
            $fail('reCAPTCHA verification failed. Please try again.');
        }
    }
}
