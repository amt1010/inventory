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
        // The page is read-only: the quote-request table itself must contain
        // no forms, buttons, or links (no edit/status/reassignment controls).
        // We scope the check to the <table>...</table> fragment rather than
        // the whole page, because the shared layout legitimately has its own
        // POST forms (navbar search, logout) and buyer/product data could
        // otherwise coincidentally contain words like "Edit" (e.g. the name
        // "Edith") or "Delete" and cause an unrelated false failure.
        $html = $response->getContent();
        preg_match('#<table.*?</table>#s', $html, $matches);
        $this->assertNotEmpty($matches, 'Expected a quote-request table in the response.');
        $tableHtml = $matches[0];

        $this->assertStringNotContainsString('<form', $tableHtml);
        $this->assertStringNotContainsString('<button', $tableHtml);
        $this->assertStringNotContainsString('<a ', $tableHtml);
    }
}
