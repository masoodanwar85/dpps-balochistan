<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    protected $model = ChecklistItem::class;

    public function definition(): array
    {
        return [
            'template_id' => ChecklistTemplate::factory(),
            'sort_order' => 1,
            'annex_code' => 'A',
            'title' => fake()->sentence(4),
            'description' => null,
            'form_reference' => null,
            'is_required' => true,
            'requires_upload' => true,
            'allowed_file_types' => 'pdf,jpg,jpeg,png',
            'max_files' => 5,
            'attestation_required' => 'none',
            'requires_validity_dates' => false,
            'must_cover_license_period' => false,
            'portal_uploadable' => true,
        ];
    }
}
