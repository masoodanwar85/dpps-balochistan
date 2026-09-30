<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Services\Documents\DocumentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['title', 'warning_reason'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['issue_date', 'expiry_date', 'warning_reason'] as $field) {
            $value = $merged[$field] ?? $this->input($field);

            if ($value === '') {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
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
                $query->where('is_active', true)->whereIn('applies_to', [$this->ownerKind(), 'any']);
            })],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'attested_by' => ['required', Rule::in(['none', 'gazetted_officer', 'notary_public', 'oath_commissioner'])],
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

    private function ownerKind(): string
    {
        if ($this->route('dealer')) {
            return 'dealer';
        }

        $document = $this->route('document');

        if ($document instanceof Document && $document->documentable_type === 'dealer') {
            return 'dealer';
        }

        return 'company';
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
