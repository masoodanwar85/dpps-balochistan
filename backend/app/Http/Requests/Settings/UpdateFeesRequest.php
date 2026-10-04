<?php

namespace App\Http\Requests\Settings;

use App\Services\Settings\FeeStructures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = collect(FeeStructures::EDITABLE)
            ->map(fn (array $slot) => $slot['entity_type'].':'.$slot['fee_type'])
            ->all();

        return [
            'fees' => ['required', 'array', 'min:1', 'max:4'],
            'fees.*.entity_type' => ['required', 'string', Rule::in(['company', 'dealer'])],
            'fees.*.fee_type' => ['required', 'string', Rule::in(['registration', 'renewal'])],
            'fees.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'fees.*.effective_from' => ['required', 'date'],
            'fees.*.notes' => ['nullable', 'string', 'max:255'],
            'fees.*' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($allowed): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $key = ($value['entity_type'] ?? '').':'.($value['fee_type'] ?? '');

                    if (! in_array($key, $allowed, true)) {
                        $fail('This fee cannot be edited here.');
                    }
                },
            ],
        ];
    }
}
