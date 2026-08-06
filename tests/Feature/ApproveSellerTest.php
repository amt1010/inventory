<?php

namespace Tests\Feature;

use App\Actions\ApproveSeller;
use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApproveSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_blocked_while_a_required_field_still_holds_the_placeholder(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'gst_number' => Seller::PLACEHOLDER,
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_email_holds_more_than_one_address(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'email' => 'a@example.com, b@example.com',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_gst_number_duplicates_another_seller(): void
    {
        Seller::factory()->create(['gst_number' => '27AAAAA0000A1Z5']);
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'gst_number' => '27AAAAA0000A1Z5',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_email_duplicates_another_seller(): void
    {
        Seller::factory()->create(['email' => 'dup@example.com']);
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'email' => 'dup@example.com',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
    }

    public function test_approval_succeeds_and_sends_email_with_no_activation_link_when_every_field_is_complete(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);
        $admin = Staff::factory()->create();

        $reasons = (new ApproveSeller())->approve($seller, $admin);

        $this->assertSame([], $reasons);
        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertSame($admin->id, $seller->approved_by);
        $this->assertNotNull($seller->approved_at);
        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->seller->is($seller) && $mail->activationUrl === null);
    }

    public function test_approval_of_a_bulk_uploaded_seller_with_no_password_set_includes_an_activation_link(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        (new ApproveSeller())->approve($seller, Staff::factory()->create());

        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->activationUrl !== null);
    }
}
