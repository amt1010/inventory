<?php

namespace Database\Factories;

use App\Models\QuoteRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteRequestFactory extends Factory
{
    protected $model = QuoteRequest::class;

    public function definition(): array
    {
        return [
            'reason' => 'Request a Quote',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'country' => 'India',
            'market' => 'Industrial',
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'message' => $this->faker->paragraph(),
            'contact_preference' => 'email',
            'status' => 'new',
        ];
    }
}
