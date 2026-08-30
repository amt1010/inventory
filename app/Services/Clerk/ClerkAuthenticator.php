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

        try {
            $payload = JWT::decode($token, JWK::parseKeySet($this->jwks($frontendApi)));
        } catch (Throwable $exception) {
            // The JWKS cache is good for an hour; if Clerk rotated its signing
            // keys in that window, a decode failure is our only signal. Bust
            // the cache and retry once against a freshly-fetched JWKS before
            // giving up, so key rotation self-heals instead of failing every
            // sign-in for up to an hour.
            Cache::forget('clerk.jwks');

            try {
                $payload = JWT::decode($token, JWK::parseKeySet($this->jwks($frontendApi)));
            } catch (Throwable $retryException) {
                throw new ClerkVerificationException('Invalid Clerk session token.', previous: $retryException);
            }
        }

        $expectedIssuer = "https://{$frontendApi}";
        if (($payload->iss ?? null) !== $expectedIssuer) {
            throw new ClerkVerificationException('Clerk session token has an unexpected issuer.');
        }

        if (empty($payload->sub)) {
            throw new ClerkVerificationException('Clerk session token is missing a subject.');
        }

        return $payload->sub;
    }

    private function jwks(string $frontendApi): array
    {
        return Cache::remember('clerk.jwks', now()->addHour(), function () use ($frontendApi) {
            $response = Http::get("https://{$frontendApi}/.well-known/jwks.json");

            if ($response->failed()) {
                throw new ClerkVerificationException('Could not fetch Clerk JWKS.');
            }

            return $response->json();
        });
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

        if (blank($email['email_address'] ?? null) || ($email['verification']['status'] ?? null) !== 'verified') {
            throw new ClerkVerificationException('Clerk user has no verified email address.');
        }

        $hasGoogleAccount = collect($response->json('external_accounts', []))
            ->contains(fn ($account) => ($account['provider'] ?? null) === 'oauth_google');

        if (! $hasGoogleAccount) {
            throw new ClerkVerificationException('Clerk identity is not linked to a Google account.');
        }

        $name = trim(($response->json('first_name') ?? '').' '.($response->json('last_name') ?? ''));

        return new ClerkIdentity(
            id: $clerkUserId,
            email: $email['email_address'],
            name: $name !== '' ? $name : null,
        );
    }
}
