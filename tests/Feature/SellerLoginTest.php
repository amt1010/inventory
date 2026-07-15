<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_load_the_dashboard_after_logging_in(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'contact_person' => 'Asha Rao',
        ]);

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertOk();
    }
}
