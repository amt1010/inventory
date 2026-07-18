<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestReceived;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_submission_creates_a_quote_request_and_sends_the_notification_email(): void
    {
        Mail::fake();

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->post(route('quote-requests.store'), [
            'product_id' => $product->id,
            'reason' => 'Request a Quote',
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'company' => 'Rao Traders',
            'country' => 'India',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'message' => 'Need pricing for 500 meters.',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('quote_request_submitted', true);

        $this->assertDatabaseHas('quote_requests', [
            'product_id' => $product->id,
            'email' => 'asha@example.com',
            'status' => 'new',
        ]);

        Mail::assertSent(QuoteRequestReceived::class, function (QuoteRequestReceived $mail) use ($product) {
            return $mail->quoteRequest->product_id === $product->id;
        });
    }

    public function test_the_quote_form_does_not_render_the_select_market_field(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'price_display' => '₹1,200 per unit',
        ]);

        $response = $this->get(url('/products/'.$product->path()));

        $response->assertOk();
        $response->assertDontSee('Select Market');
        $response->assertDontSee('name="market"', false);
    }

    public function test_a_general_inquiry_without_a_product_is_accepted(): void
    {
        Mail::fake();

        $response = $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Vikram',
            'last_name' => 'Singh',
            'email' => 'vikram@example.com',
            'phone' => '9876500000',
            'contact_preference' => 'phone',
            'privacy_policy' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quote_requests', [
            'product_id' => null,
            'email' => 'vikram@example.com',
        ]);
    }

    public function test_an_invalid_submission_is_rejected_with_validation_errors(): void
    {
        Mail::fake();

        $response = $this->post(route('quote-requests.store'), [
            'reason' => 'Request a Quote',
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone', 'contact_preference', 'privacy_policy']);
        $this->assertDatabaseCount('quote_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_a_reason_not_in_the_configured_list_is_rejected(): void
    {
        $response = $this->post(route('quote-requests.store'), [
            'reason' => 'Not A Real Reason',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_the_notification_is_sent_to_the_configured_recipient_address(): void
    {
        config(['rfq.notification_email' => 'custom-sales@example.com']);
        Mail::fake();

        $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        Mail::assertSent(QuoteRequestReceived::class, function (QuoteRequestReceived $mail) {
            return $mail->hasTo('custom-sales@example.com');
        });
    }
}
