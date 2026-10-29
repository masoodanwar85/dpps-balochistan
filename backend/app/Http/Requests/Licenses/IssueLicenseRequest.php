<?php

namespace App\Http\Requests\Licenses;

use Illuminate\Foundation\Http\FormRequest;

class IssueLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('licenses.issue') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dispatch_no')) {
            $this->merge([
                'dispatch_no' => trim((string) $this->input('dispatch_no')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dispatch_no' => ['required', 'string', 'max:50'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dispatch_no.required' => 'Enter the dispatch number.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('confirm_warnings') && mb_strlen(trim((string) $this->input('warning_reason'))) < 3) {
                $validator->errors()->add('warning_reason', 'Enter a reason of at least 3 characters.');
            }
        });
    }
}
