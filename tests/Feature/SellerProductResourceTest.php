<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Seller\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_create_a_product_scoped_to_themselves(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $category = Category::factory()->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Aerial Fiber Cable',
                'slug' => 'aerial-fiber-cable',
                'quantity' => 1000,
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'aerial-fiber-cable')->firstOrFail();

        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->price_display);
        $this->assertSame(1000, $product->quantity);
    }

    public function test_a_tampered_payload_cannot_set_price_status_or_another_sellers_id_on_create(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();
        $category = Category::factory()->create();
        $this->actingAs($seller, 'seller');

        // price_display / status / seller_id have no form fields at all in the
        // seller resource -- attempting to inject them via fillForm() proves
        // they cannot reach the database regardless, since
        // mutateFormDataBeforeCreate() stamps seller_id/status unconditionally.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Sneaky Product',
                'slug' => 'sneaky-product',
                'price_display' => '₹9,999 hacked',
                'status' => 'published',
                'seller_id' => $otherSeller->id,
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'sneaky-product')->firstOrFail();

        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->price_display);
    }

    public function test_a_seller_only_sees_their_own_products_in_the_list(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownProduct = Product::factory()->create(['seller_id' => $seller->id]);
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller, 'seller');

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$ownProduct])
            ->assertCanNotSeeTableRecords([$otherProduct]);
    }

    public function test_a_seller_cannot_open_another_sellers_product_edit_page(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller, 'seller');

        $response = $this->get("/seller/products/{$otherProduct->id}/edit");

        $response->assertNotFound();
    }

    public function test_category_options_exclude_categories_with_children(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create();
        $leaf = Category::factory()->create(['parent_id' => $parent->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($parent, $leaf) {
                $options = $field->getOptions();

                return array_key_exists($leaf->id, $options) && ! array_key_exists($parent->id, $options);
            });
    }

    public function test_editing_a_published_product_as_the_owning_seller_reverts_it_to_pending_review(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'published',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);
        $this->actingAs($seller, 'seller');

        \Livewire\Livewire::test(\App\Filament\Seller\Resources\ProductResource\Pages\EditProduct::class, [
            'record' => $product->getRouteKey(),
        ])
            ->fillForm(['short_description' => 'Updated by seller'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame('Updated by seller', $product->short_description);
        // price_display must survive the edit -- reverting to pending_review is
        // not the same as clearing Admin's prior pricing decision.
        $this->assertSame('₹1,200 – ₹1,800 per reel', $product->price_display);
    }

    public function test_editing_a_pending_review_product_as_the_owning_seller_leaves_status_unchanged(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'status' => 'pending_review',
        ]);
        $this->actingAs($seller, 'seller');

        \Livewire\Livewire::test(\App\Filament\Seller\Resources\ProductResource\Pages\EditProduct::class, [
            'record' => $product->getRouteKey(),
        ])
            ->fillForm(['short_description' => 'Still pending'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending_review', $product->fresh()->status);
    }
}
