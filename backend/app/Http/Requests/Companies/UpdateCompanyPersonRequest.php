<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('designation') && is_string($this->input('designation'))) {
            $merged['designation'] = trim($this->input('designation'));
        }

        foreach (['designation', 'appointment_date'] as $field) {
            $value = $merged[$field] ?? $this->input($field);

            if ($value === '') {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'designation' => ['nullable', 'string', 'max:100'],
            'appointment_date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
        ];
    }
}
