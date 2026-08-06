<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductSellerCodeColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_products_list_shows_the_owning_sellers_code(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        Product::factory()->create(['seller_id' => $seller->id]);

        Livewire::test(ListProducts::class)
            ->assertSee($seller->seller_code);
    }
}
