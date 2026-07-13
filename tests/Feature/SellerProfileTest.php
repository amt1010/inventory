<?php

namespace Tests\Feature;

use App\Filament\Seller\Pages\Profile;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_view_and_update_their_profile(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'company_name' => 'Old Co',
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(Profile::class)
            ->assertFormSet(['company_name' => 'Old Co'])
            ->fillForm([
                'company_name' => 'New Co',
                'contact_person' => 'Jane Doe',
                'phone' => '9999999999',
                'business_address' => '123 Industrial Estate',
                'gst_number' => $seller->gst_number,
            ])
            ->call('save');

        $seller->refresh();
        $this->assertSame('New Co', $seller->company_name);
        $this->assertSame('Jane Doe', $seller->contact_person);
    }

    public function test_a_seller_cannot_change_their_own_status_via_the_profile_form(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        // 'status' has no form field on this page at all -- fillForm() setting
        // it directly proves it cannot reach the database regardless, since the
        // save() handler only ever writes the form's own defined fields.
        Livewire::test(Profile::class)
            ->fillForm([
                'company_name' => $seller->company_name,
                'contact_person' => $seller->contact_person,
                'phone' => $seller->phone,
                'business_address' => $seller->business_address,
                'gst_number' => $seller->gst_number,
                'status' => 'suspended',
            ])
            ->call('save');

        $this->assertSame('approved', $seller->fresh()->status);
    }
}
