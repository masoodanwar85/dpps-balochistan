<?php

namespace App\Http\Requests\Dealers;

use Illuminate\Foundation\Http\FormRequest;

class CheckDealerOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dealers.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnic' => preg_replace('/\D/', '', (string) $this->input('cnic', '')) ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cnic' => ['required', 'regex:/^\d{13}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
        ];
    }
}
