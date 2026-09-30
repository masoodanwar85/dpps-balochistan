<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cnic = preg_replace('/\D/', '', (string) $this->input('cnic', '')) ?? '';
        $merged = ['cnic' => $cnic];

        foreach (['full_name', 'father_name', 'mobile', 'email', 'designation', 'institution', 'warning_reason'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['full_name', 'father_name', 'mobile', 'email', 'designation', 'institution', 'appointment_date', 'qualification_id', 'passing_year', 'warning_reason'] as $field) {
            $value = $merged[$field] ?? $this->input($field);

            if ($value === '') {
                $merged[$field] = null;
            }
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['ceo', 'director', 'technical_staff', 'contact_person', 'authorized_rep'])],
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'qualification_id' => ['nullable', 'integer', Rule::exists('qualifications', 'id')->where('is_active', true)],
            'institution' => ['nullable', 'string', 'max:200'],
            'passing_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'start_date' => ['required', 'date'],
            'designation' => ['nullable', 'string', 'max:100'],
            'appointment_date' => ['nullable', 'date'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('confirm_warnings') && mb_strlen(trim((string) $this->input('warning_reason'))) < 3) {
                $validator->errors()->add('warning_reason', 'A reason is required to confirm this warning.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
        ];
    }
}
