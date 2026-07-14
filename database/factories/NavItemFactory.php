<?php

namespace Database\Factories;

use App\Models\NavItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class NavItemFactory extends Factory
{
    protected $model = NavItem::class;

    public function definition(): array
    {
        return [
            'label' => ucwords($this->faker->words(2, true)),
            'url' => '/'.$this->faker->slug(2),
            'location' => 'header',
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }
}
