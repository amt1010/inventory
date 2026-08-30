<?php

namespace App\Services\Clerk;

use App\Exceptions\ClerkVerificationException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ClerkAuthenticator
{
    public function identify(string $token): ClerkIdentity
    {
        return $this->fetchIdentity($this->verify($token));
    }

    private function verify(string $token): string
    {
        $frontendApi = config('services.clerk.frontend_api');

        $jwks = Cache::remember('clerk.jwks', now()->addHour(), function () use ($frontendApi) {
            $response = Http::get("https://{$frontendApi}/.well-known/jwks.json");

            if ($response->failed()) {
                throw new ClerkVerificationException('Could not fetch Clerk JWKS.');
            }

            return $response->json();
        });

        try {
            $payload = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (Throwable $exception) {
            throw new ClerkVerificationException('Invalid Clerk session token.', previous: $exception);
        }

        if (empty($payload->sub)) {
            throw new ClerkVerificationException('Clerk session token is missing a subject.');
        }

        return $payload->sub;
    }

    private function fetchIdentity(string $clerkUserId): ClerkIdentity
    {
        $response = Http::withToken(config('services.clerk.secret_key'))
            ->get("https://api.clerk.com/v1/users/{$clerkUserId}");

        if ($response->failed()) {
            throw new ClerkVerificationException('Could not fetch the Clerk user record.');
        }

        $primaryEmailId = $response->json('primary_email_address_id');
        $email = collect($response->json('email_addresses', []))
            ->firstWhere('id', $primaryEmailId);

        if (blank($email['email_address'] ?? null)) {
            throw new ClerkVerificationException('Clerk user has no verified email address.');
        }

        $name = trim(($response->json('first_name') ?? '').' '.($response->json('last_name') ?? ''));

        return new ClerkIdentity(
            id: $clerkUserId,
            email: $email['email_address'],
            name: $name !== '' ? $name : null,
        );
    }
}
