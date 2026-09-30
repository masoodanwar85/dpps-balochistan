<?php

namespace App\Http\Requests\Imports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('imports.run') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx', 'max:20480'],
            'type' => ['required', Rule::in(['companies', 'dealers'])],
            'mode' => ['required', Rule::in(['dry_run', 'run'])],
        ];
    }
}
