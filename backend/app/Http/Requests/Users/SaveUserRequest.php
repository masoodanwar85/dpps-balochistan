<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user') instanceof User ? $this->route('user')->id : null;
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => [$creating ? 'required' : 'exclude', 'confirmed', Password::min(8)->letters()->numbers()],
            'user_type' => ['required', Rule::in(['staff', 'company'])],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct'],
            'district_ids' => ['present', 'array'],
            'district_ids.*' => ['integer', 'distinct', 'exists:districts,id'],
        ];
    }
}
