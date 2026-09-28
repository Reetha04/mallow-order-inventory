<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
     public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('PROD-####'),
            'price' => fake()->randomFloat(2, 100, 5000),
            'tax_percentage' => fake()->randomElement([0, 5, 12, 18]),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
