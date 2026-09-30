<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChallanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('applications.process') || $user->can('challans.verify'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challan_no' => ['required', 'string', 'max:50', Rule::unique('challans', 'challan_no')],
            'bank_name' => ['required', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'file' => ['nullable', 'file'],
            'document_type_id' => ['required_with:file', 'nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
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
            'challan_no.unique' => 'This challan number is already used.',
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
