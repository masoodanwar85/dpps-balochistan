<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('applications.process') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:verified,deficient,not_applicable'],
            'officer_remarks' => ['nullable', 'string', 'max:2000'],
            'page_count' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
