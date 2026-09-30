<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class VerifyChallanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('challans.verify') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', 'in:verified,rejected'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
