<?php

namespace App\Http\Requests\Companies;

use App\Models\CompanyPremise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyPremiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('companies.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['address', 'phone'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['district_id', 'gps_lat', 'gps_lng', 'contact_person_id', 'phone'] as $field) {
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
        $premise = $this->route('companyPremise');
        $districtId = $premise instanceof CompanyPremise ? $premise->district_id : null;

        return [
            'type' => ['required', Rule::in(['head_office', 'regional_office', 'field_office', 'warehouse'])],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where(function ($query) use ($districtId) {
                $query->where('is_active', true);

                if ($districtId) {
                    $query->orWhere('id', $districtId);
                }
            })],
            'address' => ['required', 'string'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_person_id' => ['nullable', 'integer', Rule::exists('persons', 'id')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
