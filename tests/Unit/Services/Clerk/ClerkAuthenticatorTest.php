<?php

namespace Tests\Unit\Services\Clerk;

use App\Services\Clerk\ClerkAuthenticator;
use App\Exceptions\ClerkVerificationException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClerkAuthenticatorTest extends TestCase
{
    public function test_it_verifies_a_valid_token_and_returns_the_clerk_identity(): void
    {
        [$privateKeyPem, $jwk] = $this->generateTestKey();

        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response([
                'id' => 'user_123',
                'first_name' => 'Asha',
                'last_name' => 'Rao',
                'primary_email_address_id' => 'idn_1',
                'email_addresses' => [
                    ['id' => 'idn_1', 'email_address' => 'asha@example.com'],
                ],
            ]),
        ]);

        $token = JWT::encode(
            ['sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            $privateKeyPem,
            'RS256',
            'test-key-1'
        );

        $identity = app(ClerkAuthenticator::class)->identify($token);

        $this->assertSame('user_123', $identity->id);
        $this->assertSame('asha@example.com', $identity->email);
        $this->assertSame('Asha Rao', $identity->name);
    }

    public function test_it_rejects_a_token_signed_by_an_unknown_key(): void
    {
        [, $jwk] = $this->generateTestKey();
        [$otherPrivateKeyPem] = $this->generateTestKey('other-key-1');

        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
        ]);

        $token = JWT::encode(
            ['sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            $otherPrivateKeyPem,
            'RS256',
            'other-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function generateTestKey(string $kid = 'test-key-1'): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        $jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];

        return [$privateKeyPem, $jwk];
    }
}
