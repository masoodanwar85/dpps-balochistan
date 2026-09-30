<?php

namespace Database\Factories;

use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStage>
 */
class WorkflowStageFactory extends Factory
{
    protected $model = WorkflowStage::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'company',
            'sequence' => fake()->unique()->numberBetween(1, 100),
            'code' => fake()->unique()->lexify('stage_????'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'applies_to' => 'both',
            'sla_days' => 3,
            'is_active' => true,
            'is_skippable' => false,
            'required_permission' => 'applications.process',
        ];
    }
}
