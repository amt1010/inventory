<?php

namespace Tests\Feature;

use App\Mail\SellerActivationMail;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_registration_creates_a_pending_seller_and_sends_the_activation_email(): void
    {
        Mail::fake();
        Storage::fake('public');

        $response = $this->post(route('seller.register.store'), array_merge($this->validPayload(), [
            'documents' => [UploadedFile::fake()->create('gst-certificate.pdf', 100, 'application/pdf')],
        ]));

        $response->assertRedirect(route('seller.registration.submitted'));

        $this->assertDatabaseHas('sellers', [
            'email' => 'asha@raotraders.example',
            'status' => 'pending_email_verification',
            'created_by' => 'self',
        ]);

        $seller = Seller::where('email', 'asha@raotraders.example')->firstOrFail();
        $this->assertCount(1, $seller->documents);

        Mail::assertSent(SellerActivationMail::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_registration_with_a_duplicate_email_is_rejected(): void
    {
        Seller::factory()->create(['email' => 'asha@raotraders.example']);

        $response = $this->post(route('seller.register.store'), $this->validPayload());

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('sellers', 1);
    }

    public function test_registration_with_an_invalid_gst_number_is_rejected(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload(['gst_number' => 'not-a-gstin']));

        $response->assertSessionHasErrors(['gst_number']);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_registration_with_mismatched_passwords_is_rejected(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload(['password_confirmation' => 'somethingelse']));

        $response->assertSessionHasErrors(['password']);
        $this->assertDatabaseCount('sellers', 0);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Rao Traders',
            'contact_person' => 'Asha Rao',
            'email' => 'asha@raotraders.example',
            'phone' => '9876543210',
            'business_address' => '123 Industrial Estate, Mumbai',
            'gst_number' => '27AAAAA0000A1Z5',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ], $overrides);
    }
}
