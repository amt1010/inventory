<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Seller;
use App\Models\Staff;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuantityFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_content_editor_can_set_quantity_via_the_admin_create_form(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Quantity Test Product',
                'slug' => 'quantity-test-product',
                'quantity' => 250,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'quantity-test-product')->firstOrFail();

        $this->assertSame(250, $product->quantity);
    }
}
