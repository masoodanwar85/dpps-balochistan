<?php

namespace App\Http\Requests\Catalog;

use App\Models\Tehsil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTehsilRequest extends FormRequest
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
        $tehsil = $this->route('tehsil');
        $tehsilId = $tehsil instanceof Tehsil ? $tehsil->id : null;

        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('tehsils', 'name')->where(
                    fn ($query) => $query->where('district_id', $this->integer('district_id'))
                )->ignore($tehsilId),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
