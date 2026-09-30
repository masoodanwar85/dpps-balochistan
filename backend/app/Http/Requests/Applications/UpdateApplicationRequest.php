<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('applications.create') || $user->can('applications.process'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'diary_no' => ['nullable', 'string', 'max:50'],
            'received_at' => ['nullable', 'date'],
            'total_pages' => ['nullable', 'integer', 'min:0', 'max:30000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['diary_no', 'received_at', 'total_pages'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
