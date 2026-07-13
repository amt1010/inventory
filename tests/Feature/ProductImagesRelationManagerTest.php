<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_upload_an_image_for_a_product(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create();

        Livewire::test(
            \App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager::class,
            ['ownerRecord' => $product, 'pageClass' => EditProduct::class]
        )
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('cable.jpg'),
                'is_primary' => true,
            ]);

        $this->assertSame(1, $product->images()->count());
        $this->assertTrue($product->images()->first()->is_primary);
    }
}
