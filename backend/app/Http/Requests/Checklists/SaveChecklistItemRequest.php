<?php

namespace App\Http\Requests\Checklists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checklists.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'annex_code' => ['required', 'string', 'regex:/^[A-Z0-9]{1,5}$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'form_reference' => ['nullable', 'string', 'max:30'],
            'is_required' => ['required', 'boolean'],
            'requires_upload' => ['required', 'boolean'],
            'allowed_file_types' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'max_files' => ['required', 'integer', 'min:1', 'max:99'],
            'attestation_required' => ['required', Rule::in(['none', 'gazetted_officer', 'notary_public', 'oath_commissioner'])],
            'requires_validity_dates' => ['required', 'boolean'],
            'must_cover_license_period' => ['required', 'boolean'],
            'portal_uploadable' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('annex_code')) {
            $this->merge([
                'annex_code' => strtoupper(trim((string) $this->input('annex_code'))),
            ]);
        }

        if ($this->exists('allowed_file_types')) {
            $this->merge([
                'allowed_file_types' => strtolower(str_replace(' ', '', (string) $this->input('allowed_file_types'))),
            ]);
        }
    }
}
