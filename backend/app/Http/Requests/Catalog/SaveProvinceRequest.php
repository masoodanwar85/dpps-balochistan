<?php

namespace App\Http\Requests\Catalog;

use App\Models\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lookups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $province = $this->route('province');
        $provinceId = $province instanceof Province ? $province->id : null;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('provinces', 'name')->ignore($provinceId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
