<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct as AdminEditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Seller\Resources\ProductResource\Pages\EditProduct as SellerEditProduct;
use App\Filament\Seller\Resources\ProductResource\RelationManagers\CustomAttributesRelationManager;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SellerProductRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seller_can_upload_an_image_for_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => SellerEditProduct::class,
        ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('cable.jpg'),
                'is_primary' => true,
            ]);

        $this->assertSame(1, $product->images()->count());
    }

    public function test_seller_can_add_a_custom_attribute_to_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CustomAttributesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => SellerEditProduct::class,
        ])
            ->callTableAction('create', data: [
                'label' => 'Fiber Count',
                'value' => '96',
            ]);

        $this->assertSame(1, $product->customAttributes()->count());
        $this->assertSame('Fiber Count', $product->customAttributes()->first()->label);
    }
}
