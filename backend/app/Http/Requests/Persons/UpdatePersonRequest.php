<?php

namespace App\Http\Requests\Persons;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('persons.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['full_name', 'father_name', 'mobile', 'alt_mobile', 'email', 'address', 'gender'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['father_name', 'alt_mobile', 'email', 'address', 'gender', 'date_of_birth'] as $field) {
            if (array_key_exists($field, $merged) && $merged[$field] === '') {
                $merged[$field] = null;
            }

            if (! array_key_exists($field, $merged) && $this->input($field) === '') {
                $merged[$field] = null;
            }
        }

        if ($this->exists('cnic')) {
            $raw = $this->input('cnic');
            $digits = $raw === null ? '' : preg_replace('/\D/', '', (string) $raw);
            $merged['cnic'] = $digits === '' ? null : $digits;
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
        $person = $this->route('person');
        $personId = $person instanceof Person ? $person->id : null;

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'mobile' => ['required', 'string', 'max:20'],
            'alt_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{13}$/', Rule::unique('persons', 'cnic')->ignore($personId)],
            'cnic_pending' => ['required', 'boolean'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
            'qualifications' => ['sometimes', 'array'],
            'qualifications.*.id' => ['nullable', 'integer'],
            'qualifications.*.qualification_id' => ['required', 'integer', 'exists:qualifications,id'],
            'qualifications.*.institution' => ['nullable', 'string', 'max:200'],
            'qualifications.*.passing_year' => ['nullable', 'integer', 'min:1950', 'max:'.date('Y')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pending = $this->boolean('cnic_pending');
            $cnic = $this->input('cnic');

            if ($pending && $cnic) {
                $validator->errors()->add('cnic', 'A pending CNIC must stay empty until the 13-digit CNIC is entered.');
            }

            if (! $pending && ! $cnic) {
                $validator->errors()->add('cnic', 'CNIC must be exactly 13 digits.');
            }

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
            'cnic.unique' => 'This CNIC already belongs to another person.',
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
        ];
    }
}
