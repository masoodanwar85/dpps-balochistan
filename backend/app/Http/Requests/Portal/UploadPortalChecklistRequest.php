<?php

namespace App\Http\Requests\Portal;

use App\Services\Documents\DocumentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadPortalChecklistRequest extends FormRequest
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
        $maxKilobytes = (int) ceil(app(DocumentStore::class)->maxBytes() / 1024);

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where(function ($query) {
                $query->where('is_active', true)->whereIn('applies_to', ['company', 'any']);
            })],
            'title' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('confirm_warnings') && mb_strlen(trim((string) $this->input('warning_reason'))) < 3) {
                $validator->errors()->add('warning_reason', 'A reason is required to confirm this warning.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $megabytes = (int) (app(DocumentStore::class)->maxBytes() / 1024 / 1024);

        return [
            'file.max' => "This file is larger than the maximum of {$megabytes} MB.",
        ];
    }
}
