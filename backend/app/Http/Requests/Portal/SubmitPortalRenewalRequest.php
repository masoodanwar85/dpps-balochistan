<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPortalRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.renewal.submit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cnic = preg_replace('/\D/', '', (string) $this->input('declarant_cnic', '')) ?? '';
        $this->merge([
            'declarant_cnic' => $cnic,
            'declarant_name' => trim((string) $this->input('declarant_name', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'declarant_name' => ['required', 'string', 'max:150'],
            'declarant_cnic' => ['required', 'regex:/^\d{13}$/'],
            'total_pages' => ['required', 'integer', 'min:1', 'max:9999'],
            'certified' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'declarant_cnic.regex' => 'CNIC must be exactly 13 digits.',
            'certified.accepted' => 'Certify the application before submitting it.',
        ];
    }
}
