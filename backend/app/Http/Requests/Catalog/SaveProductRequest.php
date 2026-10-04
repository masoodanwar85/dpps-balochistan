<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products_master.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'generic_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('products', 'generic_name')
                    ->where('concentration', (string) $this->input('concentration'))
                    ->where('formulation', (string) $this->input('formulation'))
                    ->ignore($productId),
            ],
            'market_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('products', 'market_name')->ignore($productId),
            ],
            'concentration' => ['required', 'string', 'max:30'],
            'formulation' => ['required', 'string', 'max:30'],
            'category' => ['required', Rule::in([
                'insecticide',
                'herbicide',
                'fungicide',
                'acaricide',
                'rodenticide',
                'nematicide',
                'plant_growth_regulator',
                'other',
            ])],
            'is_restricted' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
