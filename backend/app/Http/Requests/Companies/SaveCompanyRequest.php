<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('company') ? 'companies.update' : 'companies.create';

        return $this->user()?->can($permission) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['name', 'ntn', 'incorporation_no', 'head_office_address', 'city', 'landline', 'mobile', 'email', 'website', 'membership_no'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['ntn', 'incorporation_no', 'incorporation_date', 'landline', 'mobile', 'email', 'website', 'membership_no'] as $field) {
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
        $company = $this->route('company');
        $companyId = $company instanceof Company ? $company->id : null;
        $provinceId = $company instanceof Company ? $company->province_id : null;

        return [
            'name' => ['required', 'string', 'max:250'],
            'legal_type' => ['required', Rule::in(['private_ltd', 'public_ltd', 'partnership', 'sole_proprietor', 'other'])],
            'ntn' => ['nullable', 'string', 'max:20', Rule::unique('companies', 'ntn')->ignore($companyId)],
            'incorporation_no' => ['nullable', 'string', 'max:50'],
            'incorporation_date' => ['nullable', 'date'],
            'head_office_address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where(function ($query) use ($provinceId) {
                $query->where('is_active', true);

                if ($provinceId) {
                    $query->orWhere('id', $provinceId);
                }
            })],
            'landline' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:200'],
            'pcpa_member' => ['required', 'boolean'],
            'croplife_member' => ['required', 'boolean'],
            'membership_no' => ['nullable', 'string', 'max:50'],
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
            'ntn.unique' => 'This NTN already exists.',
        ];
    }
}
