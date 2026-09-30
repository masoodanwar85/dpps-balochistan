<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;

class EndCompanyPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('end_reason') && is_string($this->input('end_reason'))) {
            $this->merge(['end_reason' => trim($this->input('end_reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date'],
            'end_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
