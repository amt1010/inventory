<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'seller_id' => Seller::factory(),
            'category_id' => Category::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'features' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'applications' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'price_display' => '₹'.$this->faker->numberBetween(500, 2000).' – ₹'.$this->faker->numberBetween(2001, 5000),
            'quantity' => $this->faker->numberBetween(10, 1000),
            'status' => 'published',
            'sort_order' => 0,
        ];
    }
}
