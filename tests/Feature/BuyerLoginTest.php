<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_buyer_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_a_logged_in_buyer_can_log_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/logout');

        $response->assertRedirect();
        $this->assertGuest('web');
    }
}
