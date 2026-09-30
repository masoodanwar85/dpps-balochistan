<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Tehsil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tehsil>
 */
class TehsilFactory extends Factory
{
    protected $model = Tehsil::class;

    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => fake()->unique()->city(),
            'is_active' => true,
        ];
    }
}
