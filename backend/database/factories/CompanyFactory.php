<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Province;
use App\Models\User;
use App\Services\Companies\CompanyName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'company_code' => strtoupper(fake()->unique()->bothify('C####')),
            'name' => $name,
            'normalized_name' => (new CompanyName)->normalize($name),
            'legal_type' => 'private_ltd',
            'head_office_address' => fake()->streetAddress(),
            'city' => 'Quetta',
            'province_id' => Province::factory(),
            'status' => 'unlicensed',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
