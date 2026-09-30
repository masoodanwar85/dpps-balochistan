<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['brand_name', 'dpp_registration_no', 'remarks'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['dpp_registration_no', 'dpp_valid_to', 'remarks'] as $field) {
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
            'brand_name' => ['required', 'string', 'max:150'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'dpp_registration_no' => ['nullable', 'string', 'max:100'],
            'dpp_valid_to' => ['nullable', 'date'],
            'source' => ['required', Rule::in(['own_import', 'purchase_agreement'])],
            'sample_provided' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
