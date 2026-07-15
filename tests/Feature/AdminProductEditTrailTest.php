<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Mail\ProductEditReadyForAcceptance;
use App\Mail\ProductListingLive;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductEditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_editing_a_tracked_field_on_a_pending_review_product_creates_a_trail_and_requires_seller_acceptance(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'short_description' => 'Original text',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['short_description' => 'Corrected text'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_seller_acceptance', $product->status);
        $this->assertSame('Corrected text', $product->short_description);

        $trail = $product->editTrails()->latest()->first();
        $this->assertNotNull($trail);
        $this->assertSame(['old' => 'Original text', 'new' => 'Corrected text'], $trail->changes['short_description']);
        $this->assertSame($admin->id, $trail->staff_id);

        Mail::assertSent(ProductEditReadyForAcceptance::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_saving_a_pending_review_product_with_no_tracked_field_changes_creates_no_trail(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'price_display' => null,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price_display' => '₹500 – ₹800'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame(0, $product->editTrails()->count());

        Mail::assertNotSent(ProductEditReadyForAcceptance::class);
    }

    public function test_the_publish_action_is_hidden_while_a_product_awaits_seller_acceptance(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('publish', $product);
    }

    public function test_approving_a_product_with_no_pending_changes_sends_the_live_notification(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'price_display' => '₹500 – ₹800',
        ]);

        Livewire::test(ListProducts::class)
            ->callTableAction('publish', $product);

        $this->assertSame('published', $product->fresh()->status);
        Mail::assertSent(ProductListingLive::class, fn ($mail) => $mail->product->is($product));
    }

    public function test_editing_an_already_pending_seller_acceptance_product_does_not_error(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create([
            'status' => 'pending_seller_acceptance',
            'price_display' => '₹500 – ₹800',
        ]);

        // Reloading and re-saving a product already in this status must not fail
        // validation just because the currently-set status isn't a normally
        // selectable option -- mirrors the existing `published` handling.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['status' => 'pending_seller_acceptance'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending_seller_acceptance', $product->fresh()->status);
    }
}
