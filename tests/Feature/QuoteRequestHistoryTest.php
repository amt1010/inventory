<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/my-quote-requests');

        $response->assertRedirect(route('login'));
    }

    public function test_a_buyer_only_sees_their_own_quote_requests(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        QuoteRequest::factory()->create(['user_id' => $user->id, 'first_name' => 'Own', 'last_name' => 'Request']);
        QuoteRequest::factory()->create(['user_id' => $other->id, 'first_name' => 'Someone', 'last_name' => 'Else']);
        $this->actingAs($user, 'web');

        $response = $this->get('/my-quote-requests');

        $response->assertOk();
        $response->assertSee('Own Request');
        $response->assertDontSee('Someone Else');
    }

    public function test_the_page_has_no_edit_or_status_change_controls(): void
    {
        $user = User::factory()->create();
        QuoteRequest::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'web');

        $response = $this->get('/my-quote-requests');

        $response->assertOk();
        // A read-only page has no reason to contain any form that POSTs
        // anything (status changes, notes, reassignment). The navbar search
        // form uses method="GET", so this is precise to the actual
        // requirement without colliding with unrelated site chrome.
        $response->assertDontSee('method="POST"', escape: false);
    }
}
