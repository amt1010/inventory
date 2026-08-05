<?php

namespace Tests\Feature;

use App\Actions\ApproveSeller;
use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerBulkUploadActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_bulk_uploaded_seller_sends_an_activation_link_and_the_seller_can_set_a_password(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        (new ApproveSeller())->approve($seller, Staff::factory()->create());

        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->activationUrl !== null);

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->post($url, ['password' => 'newpassword123', 'password_confirmation' => 'newpassword123']);

        $response->assertOk();
        $seller->refresh();
        $this->assertTrue(Hash::check('newpassword123', $seller->password));
        $this->assertNotNull($seller->password_set_at);
        $this->assertSame('approved', $seller->status);
    }

    public function test_the_activation_link_cannot_be_reused_after_the_password_is_already_set(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }

    public function test_the_activation_link_is_rejected_while_the_seller_is_still_pending_review(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }

    public function test_a_self_registered_seller_activation_is_unaffected(): void
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
}
