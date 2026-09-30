<?php

namespace App\Http\Requests\Dealers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDealerOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dealers.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cnic = preg_replace('/\D/', '', (string) $this->input('cnic', '')) ?? '';
        $merged = ['cnic' => $cnic];

        foreach (['full_name', 'father_name', 'mobile', 'email', 'warning_reason'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['full_name', 'father_name', 'mobile', 'email', 'warning_reason'] as $field) {
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
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'start_date' => ['required', 'date'],
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
