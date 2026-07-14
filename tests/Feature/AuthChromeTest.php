<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_a_guest_sees_login_and_register_links(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'), escape: false);
        $response->assertSee(route('register'), escape: false);
    }

    public function test_a_logged_in_buyer_sees_account_links_instead(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('favorites.index'), escape: false);
        $response->assertSee(route('quote-requests.history'), escape: false);
        $response->assertDontSee(route('login'), escape: false);
    }
}
