<?php

namespace Database\Seeders;

use App\Models\NavItem;
use Illuminate\Database\Seeder;

class NavItemSeeder extends Seeder
{
    public function run(): void
    {
        NavItem::query()->firstOrCreate(
            ['label' => 'Products', 'location' => 'header'],
            ['url' => '/products', 'sort_order' => 1, 'show_category_menu' => true]
        );

        NavItem::query()->firstOrCreate(
            ['label' => 'Contact Us', 'location' => 'header'],
            ['url' => '/contact-us', 'sort_order' => 2]
        );

        NavItem::query()->firstOrCreate(
            ['label' => 'Contact Us', 'location' => 'footer'],
            ['url' => '/contact-us', 'sort_order' => 1]
        );
    }
}
