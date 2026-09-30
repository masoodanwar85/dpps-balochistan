<?php

namespace App\Http\Requests\Dealers;

use Illuminate\Foundation\Http\FormRequest;

class EndDealerOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dealers.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date'],
        ];
    }
}
