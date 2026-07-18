<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImagePrimaryToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');
    }

    public function test_toggling_primary_on_a_previously_uploaded_image_makes_it_the_sole_primary(): void
    {
        $product = Product::factory()->create();

        // Two images uploaded earlier, neither marked primary at upload time.
        Storage::disk('public')->put('product-images/first.jpg', 'x');
        Storage::disk('public')->put('product-images/second.jpg', 'x');
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);

        // Now the admin edits the first image and toggles the primary button on.
        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->callTableAction('edit', $first, data: [
            'is_primary' => true,
        ])->assertHasNoTableActionErrors();

        $this->assertTrue($first->refresh()->is_primary, 'The edited image should become primary.');
        $this->assertFalse($second->refresh()->is_primary, 'The other image should not be primary.');
    }

    public function test_the_set_as_primary_row_action_sets_primary_without_touching_the_file_field(): void
    {
        $product = Product::factory()->create();

        // Two images uploaded in a prior session. The files are NOT present on
        // this (faked) disk, mirroring the reported bug: going through the edit
        // form (with its required FileUpload) to flip the toggle is blocked, so
        // the primary setting "doesn't work". The dedicated action must not care.
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->callTableAction('setPrimary', $second)->assertHasNoTableActionErrors();

        $this->assertTrue($second->refresh()->is_primary, 'The action should make the image primary.');
        $this->assertFalse($first->refresh()->is_primary, 'The previously-primary image should be unset.');
        $this->assertSame(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
    }

    public function test_toggling_primary_to_a_different_image_moves_the_flag(): void
    {
        $product = Product::factory()->create();

        Storage::disk('public')->put('product-images/first.jpg', 'x');
        Storage::disk('public')->put('product-images/second.jpg', 'x');
        $first = $product->images()->create(['path' => 'product-images/first.jpg', 'sort_order' => 0, 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'product-images/second.jpg', 'sort_order' => 1, 'is_primary' => false]);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->callTableAction('edit', $second, data: [
            'is_primary' => true,
        ])->assertHasNoTableActionErrors();

        $this->assertFalse($first->refresh()->is_primary, 'The previously-primary image should no longer be primary.');
        $this->assertTrue($second->refresh()->is_primary, 'The newly-toggled image should be primary.');
        $this->assertSame(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
    }
}
