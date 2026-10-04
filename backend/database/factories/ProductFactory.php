<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'generic_name' => fake()->unique()->word(),
            'market_name' => fake()->unique()->words(2, true),
            'concentration' => '40%',
            'formulation' => 'EC',
            'category' => 'insecticide',
            'is_restricted' => false,
            'is_active' => true,
        ];
    }
}
