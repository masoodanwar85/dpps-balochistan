<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckCompanyPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnic' => preg_replace('/\D/', '', (string) $this->input('cnic', '')) ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['ceo', 'director', 'technical_staff', 'contact_person', 'authorized_rep'])],
            'cnic' => ['required', 'regex:/^\d{13}$/'],
        ];
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
