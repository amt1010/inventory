<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct as SellerCreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductMaterialTypeFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_set_material_type_when_creating_a_product(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
                'material_type' => 'finished_good',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'test-product')->firstOrFail();
        $this->assertSame('finished_good', $product->material_type);
    }

    public function test_material_type_is_required_on_the_admin_form(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
                'material_type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['material_type']);
    }

    public function test_a_seller_can_set_material_type_when_creating_their_own_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $category = Category::factory()->create();

        Livewire::test(SellerCreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Seller Product',
                'slug' => 'seller-product',
                'material_type' => 'raw_material',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'seller-product')->firstOrFail();
        $this->assertSame('raw_material', $product->material_type);
    }
}
