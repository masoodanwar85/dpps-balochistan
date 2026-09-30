<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('title') && is_string($this->input('title'))) {
            $merged['title'] = trim($this->input('title'));
        }

        foreach (['issue_date', 'expiry_date'] as $field) {
            $value = $this->input($field);

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
        return [
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where(function ($query) {
                $document = $this->route('document');
                $owner = $document instanceof Document && $document->documentable_type === 'dealer' ? 'dealer' : 'company';
                $query->where('is_active', true)->whereIn('applies_to', [$owner, 'any']);
            })],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'attested_by' => ['required', Rule::in(['none', 'gazetted_officer', 'notary_public', 'oath_commissioner'])],
        ];
    }
}
