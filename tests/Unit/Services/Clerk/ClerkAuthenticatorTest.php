<?php

namespace Tests\Unit\Services\Clerk;

use App\Services\Clerk\ClerkAuthenticator;
use App\Exceptions\ClerkVerificationException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClerkAuthenticatorTest extends TestCase
{
    /**
     * Keypair A — the "correct"/trusted key used in the JWKS Clerk actually serves.
     * Fixed so signing/verifying in these tests never depends on openssl.cnf being
     * discoverable at runtime (openssl_pkey_new()/openssl_pkey_export() do, but
     * JWT::encode() against a PEM string and JWK::parseKeySet() do not).
     */
    private const PRIVATE_KEY_A = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDkK+qlhnl0w45t
    Gqcr916J15VkLTqW+ke+Dck9wkmgK/JsEuGmFx0wREtTI4fRPjlPNgKMElRoGDqz
    GWFbdt1taaqzE5lVcIz81LpyDTWYIbxIa/7TKOw2ixK6wEXM3/E9Wo40fnnrnpEX
    BJO/lDozAMaaBofsWqhLl7mvd4/ZILsGkkmzsBjIAS765IFlF5nYZmM19mUqYLwc
    h3IRdRUUI33cm4zX8GkRlKD8lWB8/ZFdQAg3OKWRHPny04OrBcNRPtLbsKzwKZp+
    r54cfVJ8RKfAtueZc8aPXTUiiLKGMc4xS17gTZr5yPKXQU7qzj6hMT6cjNnRyvOI
    M9b/e8HxAgMBAAECggEAD4DgZ7DUbdefxNALzdP4C3unffAIzBtjZol/RAAAiq6R
    wUAjLurhI9dwGs0OPGxy7mvoDmZsb8o9qs+tqs5Py1Bjtdk+EO0d10wJAxjcrGKW
    bYFRBj4AltAqTKAl3f7VYGrXwL9bP2Q9zYeVFm5W6gJCfFK3R5r9Vm2Pw1lnX8RV
    sjWhAx+8+ABKngsBTRxuRxfKiuEfLesbI+N37CgWa3gU1TCwCR90ptBSeBUA4wjL
    HT3GseC8OGEwLmoOzXYHH/1uciXlps0tgZAKzNSR+S+4oBSmfxbzHpWEKsktNWvA
    RRtEMXGA4GNt9QZMVJgG8Yr2uU5X6gR/HrU9q0zFsQKBgQD7gJfkOMgso5BLt8Gc
    0hXP0GaGBh9ZUrqiUeNp13QQCYkZG67Q2vFdkal9KiKysAU42anlf76CBqNJj+k8
    7UxldoEOg9+cSB9xT4SMrDHqS3AcWQ/Yz1tozzkz2xNZL6LmwX4UmNZIRRlfx/Lr
    frRYIfPqnygmxCRSeeL0zPnthQKBgQDoQIMmmdl8vjXAzpHT1pKGZMiF0nBspvBf
    pDPwVSNsM+i78waIt/sXU7XyL377wQfb/XhcHGdVjM8V/i4Z7VXC63BNxEeBLiwP
    2Kw5zbGE8wv5n5LnQHCoZgSwB4Kv00FA9K+5MVmj4w/n3i5nZS6Qzh7JMe9LJjuT
    ege8R94ofQKBgQDn8y8iomOrF6aKzoxXr0HCiXckgB0FalEKRu+vu68k40Z3y5os
    sOAN5bsk3mll1wTZ65TPPkNa8/hAbeMz976PjP11f5YJMlMdU7LxchYO+UgKPPFq
    icLKJOOiuZdcl5xrqWQ4ZsSpnmDKf0PAgPiel6G3btW++wJstlDkaO7PaQKBgC6L
    bz03K/zx6bfgLh10LR096WnYSKudsSKZt8b8aQLwTD4OcErKBEoifp4wopQ1lSuj
    WpGrJ5Jfi8jujbKoe716jaEoKuRaqn3qgGl9LYxlYQr/zeMGaQ12lI3qk2hFQBiS
    cPz+ROaxRKjFQCt8fZ6LkGPl2/0Fhn8Bv7cd+AnJAoGAaJ9j0diZOKfKP2NHbPLJ
    OQnhW2Y5Hnkyd1viEHxCgvcHYauTORZVLBQVeC20sXYcvt4BphuSITT7nVlYksSC
    vzDUVERbDuZ3SIv6YwJzooBSNfnR/azZUCWhB70s4ImxoZMWz0kbwPa3I4R3rHUn
    IHhTRksBtZWJ+sj1Glv2Ys8=
    -----END PRIVATE KEY-----
    PEM;

    private const JWK_N_A = '5CvqpYZ5dMOObRqnK_deideVZC06lvpHvg3JPcJJoCvybBLhphcdMERLUyOH0T45TzYCjBJUaBg6sxlhW3bdbWmqsxOZVXCM_NS6cg01mCG8SGv-0yjsNosSusBFzN_xPVqONH55656RFwSTv5Q6MwDGmgaH7FqoS5e5r3eP2SC7BpJJs7AYyAEu-uSBZReZ2GZjNfZlKmC8HIdyEXUVFCN93JuM1_BpEZSg_JVgfP2RXUAINzilkRz58tODqwXDUT7S27Cs8Cmafq-eHH1SfESnwLbnmXPGj101IoiyhjHOMUte4E2a-cjyl0FO6s4-oTE-nIzZ0crziDPW_3vB8Q';

    private const JWK_E_A = 'AQAB';

    /**
     * Keypair B — an "other" key whose public half is never in the JWKS. Used
     * only to sign tokens that must be rejected as unknown-key signatures.
     */
    private const PRIVATE_KEY_B = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDI4R5qQFI9eukJ
    ctaG/T765UAyTf2tCqIOSEbCaBNisOeZbeZhpY2qv8TJG4HcBx3cQuZLyPslo6pg
    lEr7sHg9EFQXVV6h3tDMG8bbrAc4aMDkBYJrOWRQT9iC19kGXfzi5lJsefAklAOc
    s+oDq6Pk9Wv+C2Zt/aXOwzyIH4b3PZK7z0tB3dDWi8pjMU5dRLemIvI5NvcM20tt
    lD+qy6IQiUdu4fW/iOfqh8eie8tlQUVZqZuhvNIDhnVWE2sFFjWRwcQt6PK9SDXl
    EQA4waxsHp6r//6n7nRYm3z388jaB+Vjusv9qpCtZ+1euRuJJKNJdL1/XP6QX4ip
    peJSxtkhAgMBAAECggEATyx/ZuRkJaw4hktXZ4wQEyZhV5JqvhW3SsbM8NnBbkAI
    gG4TBACS5i5AWv13AOhjKgnKKCuWZT7tK7S3Gx1yPqsdYbb5nfYquI/oIHPcwqxy
    /kx3m1hbA9Z8oRF/DeXkgu/Bo9SpxFj8VXqJ7Rls9xSOFGqc3BRBkk1cIdC/Stms
    NnexsvIOIO4khrNOeF3I6HydTVGUYHCP7amKVpFb7pWjV3Z8p+ie2E24cbzDy3ZB
    P/rxeAGNhWUtLAVKtIFMGhBDF+GMzqHU+PVXtkjuYotGKUpbIhPN8zIXk7wbn0rA
    cb+mieAMM5i/TUNUiK9noJ8kEtbigXYo4Nv5jdg8AwKBgQDzJSvGMjS4yJGKWR6D
    M90D+F2U2aVEIGc2eN4miZ6616ttEsl6Zhd6t9qSp7d8XL4DhgE6zScsjQFGfdWI
    Rbz4vJtym16D5e2XjAoJaMAaDM2JfW75rf50Cy4nbsgJBTGtNuCdJS1zSmGqk7pU
    HQESivm8U6xDhyzj08c1+sEFQwKBgQDTf+eFVRP0v2W5EPK8TIBkTkVGcMKyICnY
    udi9wZgSwXdKypoz4QJ6XXsEMbmTNkzKWaY7qmEdM0xsYjW407IDIi604HpC/oPW
    HN2hLB7NSDukyw6p9iMpB94zPchnvtnb+vXmipqodAbhn20bjgO4EdjgnS8TFvKH
    HORNqwhPywKBgGKRhxVp9QWUCaxURJJbzBV38jgNo3niyPTEOwrUb4y/MbeeDh+Y
    k5wkPG+HnlLEJiO3h3gXAvKElyfEi3QbEWikzT/AzKgb5h2xn8AAvx/QYOKD/yEo
    8CaLAcLqnh1KBcF6pcQO9kXuuXk1OiwvNegWfvdh1Evr2L7jc7bmWKmRAoGBAIKa
    m0eUsAwVHSXZN2vb+fT2+IR1IWWJww2YRiV3pQb//UBsOCkNK9CQZWTDqAsmHuld
    zu9NWUcE5I6RXwdRgr24oAsGC7nPHW5cyOe8LRErJ9mtotKFslSmDSqrXlPiYPoc
    0TiaIsMfUxiEsIWxfs5uBvU9W1J4ey/AQaNMmIddAoGBANm2sF0gZgvc5jNORt5b
    EBJCHptpwoTcidMRba1irnFgRCHQ/b3yW8WtkYtJjMJDb2dflYMpzHHmkV0waeyu
    I7U4eGnuTBwIdi/ss5wNEzwXZJKDuvwiGSx12+AK8AR7/6f/TG3ILuV8oCmMYKtC
    omrK6MH0O5pr7Ay3nbLT2TqC
    -----END PRIVATE KEY-----
    PEM;

    private function jwkA(): array
    {
        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => 'test-key-1',
            'n' => self::JWK_N_A,
            'e' => self::JWK_E_A,
        ];
    }

    private function validUserResponse(): array
    {
        return [
            'id' => 'user_123',
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'primary_email_address_id' => 'idn_1',
            'email_addresses' => [
                [
                    'id' => 'idn_1',
                    'email_address' => 'asha@example.com',
                    'verification' => ['status' => 'verified'],
                ],
            ],
            'external_accounts' => [
                ['provider' => 'oauth_google'],
            ],
        ];
    }

    public function test_it_verifies_a_valid_token_and_returns_the_clerk_identity(): void
    {
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$this->jwkA()]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response($this->validUserResponse()),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://test.clerk.accounts.dev', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_A,
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
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$this->jwkA()]]),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://test.clerk.accounts.dev', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_B,
            'RS256',
            'other-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    public function test_it_rejects_a_token_with_an_unexpected_issuer(): void
    {
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$this->jwkA()]]),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://evil.example.com', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_A,
            'RS256',
            'test-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    public function test_it_rejects_an_identity_with_an_unverified_email(): void
    {
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$this->jwkA()]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response([
                'id' => 'user_123',
                'first_name' => 'Asha',
                'last_name' => 'Rao',
                'primary_email_address_id' => 'idn_1',
                'email_addresses' => [
                    [
                        'id' => 'idn_1',
                        'email_address' => 'asha@example.com',
                        'verification' => ['status' => 'unverified'],
                    ],
                ],
                'external_accounts' => [
                    ['provider' => 'oauth_google'],
                ],
            ]),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://test.clerk.accounts.dev', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_A,
            'RS256',
            'test-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    public function test_it_rejects_an_identity_with_no_google_external_account(): void
    {
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$this->jwkA()]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response([
                'id' => 'user_123',
                'first_name' => 'Asha',
                'last_name' => 'Rao',
                'primary_email_address_id' => 'idn_1',
                'email_addresses' => [
                    [
                        'id' => 'idn_1',
                        'email_address' => 'asha@example.com',
                        'verification' => ['status' => 'verified'],
                    ],
                ],
                'external_accounts' => [],
            ]),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://test.clerk.accounts.dev', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_A,
            'RS256',
            'test-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    public function test_it_busts_the_jwks_cache_and_retries_once_on_a_decode_failure(): void
    {
        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        // Seed the cache with a stale JWKS — a key under a different kid, as if
        // Clerk rotated keys since the cache was last populated. The exact key
        // material doesn't matter (n/e need only be well-formed integers, not a
        // matching keypair): what matters is that its kid ('other-key-1') can't
        // satisfy a token signed with kid 'test-key-1', forcing the decode-and-retry
        // path. Keeping this static (rather than derived via openssl_pkey_get_*
        // at runtime) is the whole point of fix #6 — no openssl.cnf dependency.
        Cache::put('clerk.jwks', [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'other-key-1',
                'n' => self::JWK_N_A,
                'e' => self::JWK_E_A,
            ]],
        ], now()->addHour());

        // Only fake the JWKS endpoint once — it should only be hit on the retry,
        // after Cache::forget clears the stale entry seeded above.
        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::sequence()
                ->push(['keys' => [$this->jwkA()]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response($this->validUserResponse()),
        ]);

        $token = JWT::encode(
            ['iss' => 'https://test.clerk.accounts.dev', 'sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            self::PRIVATE_KEY_A,
            'RS256',
            'test-key-1'
        );

        $identity = app(ClerkAuthenticator::class)->identify($token);

        $this->assertSame('user_123', $identity->id);
        $this->assertSame('asha@example.com', $identity->email);
    }
}
