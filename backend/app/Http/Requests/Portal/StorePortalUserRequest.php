<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.users.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['name', 'email', 'mobile'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(['Company Admin', 'Company Staff'])],
        ];
    }
}
