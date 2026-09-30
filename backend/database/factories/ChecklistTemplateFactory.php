<?php

namespace Database\Factories;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    protected $model = ChecklistTemplate::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'company',
            'application_type' => 'new',
            'version_no' => fake()->unique()->numberBetween(1, 30000),
            'name' => fake()->words(3, true),
            'status' => 'draft',
            'published_at' => null,
            'published_by' => null,
        ];
    }
}
