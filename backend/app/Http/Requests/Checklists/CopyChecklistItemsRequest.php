<?php

namespace App\Http\Requests\Checklists;

use App\Models\ChecklistTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyChecklistItemsRequest extends FormRequest
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
        $template = $this->route('checklistTemplate');
        $templateId = $template instanceof ChecklistTemplate ? $template->id : 0;

        return [
            'source_template_id' => ['required', 'integer', 'exists:checklist_templates,id', Rule::notIn([$templateId])],
        ];
    }
}
