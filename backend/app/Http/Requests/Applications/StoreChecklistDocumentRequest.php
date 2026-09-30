<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'document_type_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
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
