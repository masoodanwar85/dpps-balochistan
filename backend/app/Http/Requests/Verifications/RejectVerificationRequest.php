<?php

namespace App\Http\Requests\Verifications;

use Illuminate\Foundation\Http\FormRequest;

class RejectVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = match ($this->route('type')) {
            'document' => 'documents.verify',
            'product' => 'products.verify',
            'staff' => 'staff.verify',
            default => '',
        };

        return $user !== null && $permission !== '' && $user->can($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Enter a reason.',
            'reason.min' => 'Enter a reason of at least 3 characters.',
            'reason.max' => 'Enter a reason of at most 255 characters.',
        ];
    }
}
