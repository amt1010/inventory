<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkBuyerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_visitor_is_registered_and_logged_in_via_google(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_123', 'jane@example.com', 'Jane Buyer'));
        });

        $response = $this->postJson('/auth/clerk/buyer', ['token' => 'valid-token']);

        $response->assertOk();
        $response->assertJson(['redirect' => route('home')]);
        $this->assertAuthenticated('web');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('user_123', $user->clerk_user_id);
        $this->assertSame('Jane Buyer', $user->name);
        $this->assertNull($user->password);
    }

    public function test_an_existing_password_account_is_linked_by_email_instead_of_duplicated(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'clerk_user_id' => null]);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_123', 'jane@example.com', 'Jane Buyer'));
        });

        $response = $this->postJson('/auth/clerk/buyer', ['token' => 'valid-token']);

        $response->assertOk();
        $this->assertAuthenticatedAs($user->fresh(), 'web');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('user_123', $user->fresh()->clerk_user_id);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $response = $this->postJson('/auth/clerk/buyer', []);

        $response->assertStatus(422);
        $this->assertGuest('web');
    }
}
