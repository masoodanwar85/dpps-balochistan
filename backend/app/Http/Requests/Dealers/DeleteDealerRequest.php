<?php

namespace App\Http\Requests\Dealers;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dealers.delete') ?? false;
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
}
