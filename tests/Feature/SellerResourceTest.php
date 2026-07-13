<?php

namespace Tests\Feature;

use App\Filament\Resources\SellerResource\Pages\ListSellers;
use App\Mail\SellerApproved;
use App\Mail\SellerRejected;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SellerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_content_editor_gets_a_403_visiting_the_sellers_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/sellers');

        $response->assertForbidden();
    }

    public function test_approving_a_pending_seller_sets_status_and_sends_email(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        Livewire::test(ListSellers::class)
            ->callTableAction('approve', $seller);

        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertSame($admin->id, $seller->approved_by);
        $this->assertNotNull($seller->approved_at);

        Mail::assertSent(SellerApproved::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_rejecting_a_pending_seller_stores_the_reason_and_sends_email(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        Livewire::test(ListSellers::class)
            ->callTableAction('reject', $seller, data: ['rejection_reason' => 'Documents did not match business name.']);

        $seller->refresh();
        $this->assertSame('rejected', $seller->status);
        $this->assertSame('Documents did not match business name.', $seller->rejection_reason);

        Mail::assertSent(SellerRejected::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_approve_and_reject_are_not_available_for_a_seller_not_pending_approval(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'approved']);

        Livewire::test(ListSellers::class)
            ->assertTableActionHidden('approve', $seller)
            ->assertTableActionHidden('reject', $seller);
    }
}
