<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_is_logged_in_immediately(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated('web');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Buyer', $user->name);
    }

    public function test_registration_with_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_registration_with_mismatched_passwords_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'not-matching',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest('web');
    }
}
