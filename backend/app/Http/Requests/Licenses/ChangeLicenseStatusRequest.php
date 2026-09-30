<?php

namespace App\Http\Requests\Licenses;

use Illuminate\Foundation\Http\FormRequest;

class ChangeLicenseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (str_ends_with($this->path(), '/suspend')) {
            return $user->can('licenses.suspend');
        }

        if (str_ends_with($this->path(), '/cancel')) {
            return $user->can('licenses.cancel');
        }

        return $user->can('licenses.restore');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'order_no' => ['required', 'string', 'max:100'],
            'effective_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_no.required' => 'Enter the order number.',
        ];
    }
}
