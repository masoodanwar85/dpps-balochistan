<?php

namespace App\Http\Requests\Workflow;

use App\Support\PermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workflow.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.id' => ['required', 'integer', 'distinct', 'exists:workflow_stages,id'],
            'stages.*.applies_to' => ['required', Rule::in(['new', 'renewal', 'both'])],
            'stages.*.sla_days' => ['nullable', 'integer', 'min:1', 'max:999'],
            'stages.*.is_active' => ['required', 'boolean'],
            'stages.*.is_skippable' => ['required', 'boolean'],
            'stages.*.required_permission' => ['required', 'string', Rule::in(PermissionMatrix::keys())],
        ];
    }
}
