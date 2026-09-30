<?php

namespace Database\Factories;

use App\Models\Qualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qualification>
 */
class QualificationFactory extends Factory
{
    protected $model = Qualification::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_agriculture_degree' => false,
            'is_active' => true,
        ];
    }
}
