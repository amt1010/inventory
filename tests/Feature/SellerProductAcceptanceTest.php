<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\ListProducts;
use App\Mail\ProductListingLive;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_accept_pending_changes_and_the_product_goes_live(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);
        $trail = $product->editTrails()->create([
            'changes' => ['short_description' => ['old' => 'Old', 'new' => 'New']],
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->callTableAction('acceptChanges', $product);

        $product->refresh();
        $this->assertSame('published', $product->status);
        $this->assertNotNull($trail->fresh()->accepted_at);
        Mail::assertSent(ProductListingLive::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_accepting_changes_without_a_price_falls_back_to_pending_review(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_seller_acceptance',
            'price_display' => null,
        ]);
        $product->editTrails()->create(['changes' => ['short_description' => ['old' => 'Old', 'new' => 'New']]]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->callTableAction('acceptChanges', $product);

        $this->assertSame('pending_review', $product->fresh()->status);
        Mail::assertNotSent(ProductListingLive::class);
    }

    public function test_the_accept_changes_action_is_hidden_for_products_not_awaiting_acceptance(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'status' => 'pending_review']);
        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('acceptChanges', $product);
    }
}
