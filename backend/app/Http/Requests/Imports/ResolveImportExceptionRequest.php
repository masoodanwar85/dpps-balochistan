<?php

namespace App\Http\Requests\Imports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveImportExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('imports.resolve') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['skip', 'merge', 'new', 'fix'])],
            'target_id' => ['nullable', 'integer', 'required_if:resolution,merge'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
