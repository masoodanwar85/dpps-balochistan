<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'category' => 'other',
            'applies_to' => 'any',
            'has_expiry' => false,
            'is_active' => true,
        ];
    }
}
