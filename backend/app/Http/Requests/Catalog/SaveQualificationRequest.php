<?php

namespace App\Http\Requests\Catalog;

use App\Models\Qualification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lookups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $qualification = $this->route('qualification');
        $qualificationId = $qualification instanceof Qualification ? $qualification->id : null;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('qualifications', 'name')->ignore($qualificationId)],
            'is_agriculture_degree' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
