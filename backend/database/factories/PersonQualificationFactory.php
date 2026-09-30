<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonQualification;
use App\Models\Qualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonQualification>
 */
class PersonQualificationFactory extends Factory
{
    protected $model = PersonQualification::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'qualification_id' => Qualification::factory(),
            'institution' => null,
            'passing_year' => null,
            'degree_document_id' => null,
        ];
    }
}
