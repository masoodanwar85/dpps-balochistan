<?php

namespace App\Http\Requests\Checklists;

use Illuminate\Foundation\Http\FormRequest;

class ReorderChecklistItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checklists.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
        ];
    }
}
