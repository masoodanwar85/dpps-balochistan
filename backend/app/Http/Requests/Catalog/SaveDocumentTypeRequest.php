<?php

namespace App\Http\Requests\Catalog;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lookups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documentType = $this->route('documentType');
        $documentTypeId = $documentType instanceof DocumentType ? $documentType->id : null;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('document_types', 'name')->ignore($documentTypeId)],
            'category' => ['required', Rule::in([
                'application',
                'cnic',
                'license',
                'challan',
                'inspection',
                'correspondence',
                'agreement',
                'qualification',
                'other',
            ])],
            'applies_to' => ['required', Rule::in(['company', 'dealer', 'person', 'any'])],
            'has_expiry' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
