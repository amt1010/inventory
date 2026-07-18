<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPriceFormattingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_unformatted_price_is_saved_with_rupee_symbol_and_indian_grouping(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'staff');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Priced Product',
                'slug' => 'priced-product',
                'price_display' => '100000 - 180000 per reel',
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'priced-product')->firstOrFail();

        $this->assertSame('₹1,00,000 - ₹1,80,000 per reel', $product->price_display);
    }
}
