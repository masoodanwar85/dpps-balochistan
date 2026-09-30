<?php

namespace App\Http\Requests\Companies;

use App\Models\CompanyAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('companies.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('description') && is_string($this->input('description'))) {
            $merged['description'] = trim($this->input('description'));
        }

        foreach (['district_id', 'estimated_value'] as $field) {
            $value = $this->input($field);

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
        $asset = $this->route('companyAsset');
        $districtId = $asset instanceof CompanyAsset ? $asset->district_id : null;

        return [
            'asset_type' => ['required', Rule::in(['movable', 'immovable'])],
            'description' => ['required', 'string', 'max:255'],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where(function ($query) use ($districtId) {
                $query->where('is_active', true);

                if ($districtId) {
                    $query->orWhere('id', $districtId);
                }
            })],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
