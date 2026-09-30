<?php

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dealer>
 */
class DealerFactory extends Factory
{
    protected $model = Dealer::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'dealer_code' => strtoupper(fake()->unique()->bothify('D####')),
            'shop_name' => $name,
            'normalized_name' => mb_strtolower($name),
            'district_id' => District::factory(),
            'tehsil_id' => null,
            'business_address' => fake()->streetAddress(),
            'status' => 'unlicensed',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
