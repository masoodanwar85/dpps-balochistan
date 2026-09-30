<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class StorePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('penalties.enter') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penalty_type' => ['required', 'in:late_renewal,no_technical_staff,restoration,other'],
            'basis' => ['required', 'string', 'max:255'],
            'standard_amount' => ['nullable', 'numeric', 'min:0'],
            'final_amount' => ['required', 'numeric', 'min:0'],
            'waiver_reason' => ['nullable', 'string', 'max:2000'],
            'waiver_order_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}
