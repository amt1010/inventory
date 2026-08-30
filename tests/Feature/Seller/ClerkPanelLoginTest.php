<?php

namespace Tests\Feature\Seller;

use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkPanelLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_clerk_linked_seller_can_log_in(): void
    {
        $seller = Seller::factory()->create([
            'clerk_user_id' => 'user_456',
            'status' => 'approved',
        ]);

        $this->mock(ClerkAuthenticator::class, function ($mock) use ($seller) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_456', $seller->email, 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertOk();
        $this->assertAuthenticatedAs($seller->fresh(), 'seller');
    }

    public function test_an_unlinked_google_account_is_rejected(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_999', 'nobody@example.com', 'Nobody'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertGuest('seller');
    }

    public function test_a_not_yet_approved_seller_is_rejected(): void
    {
        Seller::factory()->create([
            'clerk_user_id' => 'user_456',
            'status' => 'pending_admin_approval',
        ]);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertGuest('seller');
    }
}
