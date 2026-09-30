<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => '1',
            'data_type' => 'string',
            'group' => 'general',
            'label' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_ui_editable' => true,
        ];
    }
}
