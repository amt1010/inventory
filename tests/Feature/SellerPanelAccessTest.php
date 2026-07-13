<?php

namespace Tests\Feature;

use App\Models\Seller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);

        $this->assertTrue($seller->canAccessPanel(Filament::getPanel('seller')));
    }

    public function test_a_pending_seller_cannot_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        $this->assertFalse($seller->canAccessPanel(Filament::getPanel('seller')));
    }

    public function test_a_rejected_seller_cannot_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'rejected']);

        $this->assertFalse($seller->canAccessPanel(Filament::getPanel('seller')));
    }
}
