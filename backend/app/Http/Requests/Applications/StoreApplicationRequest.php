<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('applications.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'licensable_type' => ['required', 'in:company,dealer'],
            'licensable_id' => ['required', 'integer'],
            'application_type' => ['required', 'in:new,renewal'],
            'diary_no' => ['nullable', 'string', 'max:50'],
            'received_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('diary_no') === '') {
            $this->merge(['diary_no' => null]);
        }

        if ($this->input('received_at') === '') {
            $this->merge(['received_at' => null]);
        }
    }
}
