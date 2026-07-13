<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerDocumentFactory extends Factory
{
    protected $model = SellerDocument::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'label' => $this->faker->words(2, true),
            'file_path' => 'seller-documents/'.$this->faker->uuid().'.pdf',
            'uploaded_at' => now(),
        ];
    }
}
