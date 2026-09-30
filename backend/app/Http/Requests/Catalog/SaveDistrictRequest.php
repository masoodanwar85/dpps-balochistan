<?php

namespace App\Http\Requests\Catalog;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lookups.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $district = $this->route('district');
        $districtId = $district instanceof District ? $district->id : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('districts', 'code')->ignore($districtId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
