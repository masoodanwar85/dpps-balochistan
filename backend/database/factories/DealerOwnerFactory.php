<?php

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealerOwner>
 */
class DealerOwnerFactory extends Factory
{
    protected $model = DealerOwner::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'person_id' => Person::factory(),
            'start_date' => '2020-01-15',
            'end_date' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
