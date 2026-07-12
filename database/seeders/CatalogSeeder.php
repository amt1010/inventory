<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $seller = Seller::factory()->create(['company_name' => 'Demo Supplier Co.']);

        $fiberOpticCable = Category::factory()->create([
            'name' => 'Fiber Optic Cable',
            'slug' => 'fiber-optic-cable',
            'parent_id' => null,
        ]);

        $aerial = Category::factory()->create([
            'name' => 'Aerial',
            'slug' => 'aerial',
            'parent_id' => $fiberOpticCable->id,
        ]);

        $opgw = Category::factory()->create([
            'name' => 'OPGW',
            'slug' => 'opgw',
            'parent_id' => $aerial->id,
        ]);

        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $opgw->id,
            'name' => 'CentraCore Optical Ground Wire (OPGW)',
            'slug' => 'centracore-opgw-cable',
            'status' => 'published',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);

        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $opgw->id,
            'name' => 'HexaCore Optical Ground Wire (OPGW)',
            'slug' => 'hexacore-opgw-cable',
            'status' => 'published',
            'price_display' => '₹1,500 – ₹2,100 per reel',
        ]);
    }
}
