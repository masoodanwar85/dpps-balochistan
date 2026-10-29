<?php

namespace App\Http\Requests\Licenses;

use Illuminate\Foundation\Http\FormRequest;

class StorePreviousLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('licenses.issue') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['license_no', 'dispatch_no'] as $field) {
            if ($this->has($field)) {
                $merged[$field] = trim((string) $this->input($field));
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
            'licensable_type' => ['required', 'in:company,dealer'],
            'licensable_id' => ['required', 'integer'],
            'license_no' => ['required', 'string', 'max:100'],
            'dispatch_no' => ['required', 'string', 'max:50'],
            'license_kind' => ['required', 'in:registration,renewal'],
            'valid_to' => ['required', 'date', 'before_or_equal:'.now()->endOfYear()->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'licensable_id.required' => 'Choose a company or dealer.',
            'license_no.required' => 'Enter the license number.',
            'dispatch_no.required' => 'Enter the dispatch number.',
            'valid_to.before_or_equal' => 'The end date must be '.now()->endOfYear()->format('d-m-Y').' or earlier.',
        ];
    }
}
