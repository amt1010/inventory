<?php

namespace Tests\Feature\Seller;

use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkRegistrationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_identity_is_stashed_in_session_and_redirects_to_the_registration_form(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/register', ['token' => 'valid-token']);

        $response->assertOk();
        $response->assertJson(['redirect' => route('seller.register')]);
        $this->assertSame([
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ], session('seller_clerk_identity'));
    }

    public function test_an_email_already_used_by_a_seller_is_rejected(): void
    {
        Seller::factory()->create(['email' => 'asha@raotraders.example']);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/register', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertNull(session('seller_clerk_identity'));
    }
}
