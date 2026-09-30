<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyPerson>
 */
class CompanyPersonFactory extends Factory
{
    protected $model = CompanyPerson::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'person_id' => Person::factory(),
            'role' => 'technical_staff',
            'designation' => null,
            'appointment_date' => null,
            'start_date' => '2021-04-01',
            'end_date' => null,
            'end_reason' => null,
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
            'source' => 'office',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
