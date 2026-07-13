<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_self_registered_seller_moves_to_pending_admin_approval_after_activating(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'self',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $seller->refresh();
        $this->assertSame('pending_admin_approval', $seller->status);
        $this->assertNotNull($seller->email_verified_at);
    }

    public function test_an_admin_created_seller_sees_a_set_password_form_on_the_activation_link(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'admin',
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.set-password');
        $this->assertSame('pending_email_verification', $seller->fresh()->status);
    }

    public function test_an_admin_created_seller_becomes_approved_immediately_after_setting_a_password(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'admin',
        ]);

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->post($url, [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();
        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertNotNull($seller->approved_at);
        $this->assertTrue(Hash::check('newpassword123', $seller->password));
    }

    public function test_a_request_without_a_valid_signature_is_rejected(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending_email_verification']);

        $response = $this->get(route('seller.activate', ['seller' => $seller->id]));

        $response->assertForbidden();
    }

    public function test_an_already_activated_seller_sees_the_invalid_link_page(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }
}
