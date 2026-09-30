<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'cnic' => fake()->unique()->numerify('#############'),
            'cnic_pending' => false,
            'full_name' => $name,
            'normalized_name' => mb_strtolower($name),
            'father_name' => null,
            'gender' => null,
            'date_of_birth' => null,
            'mobile' => fake()->unique()->numerify('03#########'),
            'alt_mobile' => null,
            'email' => null,
            'address' => null,
            'photo_path' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
