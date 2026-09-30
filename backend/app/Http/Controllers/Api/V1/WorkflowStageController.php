<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Workflow\UpdateWorkflowStagesRequest;
use App\Models\WorkflowStage;
use App\Services\Workflow\WorkflowStageWriter;
use App\Support\ApiResponse;
use App\Support\PermissionMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowStageController
{
    public function __construct(private WorkflowStageWriter $stages) {}

    public function index(Request $request): JsonResponse
    {
        $query = WorkflowStage::query()->orderBy('entity_type')->orderBy('sequence');

        if ($request->filled('filter.entity_type')) {
            $query->where('entity_type', $request->string('filter.entity_type')->value());
        }

        return ApiResponse::success(
            $query->get()->map(fn (WorkflowStage $stage) => $this->payload($stage))->values(),
            ['permissions' => PermissionMatrix::keys()],
        );
    }

    public function update(UpdateWorkflowStagesRequest $request): JsonResponse
    {
        foreach ($request->validated('stages') as $row) {
            $stage = WorkflowStage::query()->findOrFail($row['id']);
            $this->stages->update($stage, [
                'applies_to' => $row['applies_to'],
                'sla_days' => $row['sla_days'],
                'is_active' => $row['is_active'],
                'is_skippable' => $row['is_skippable'],
                'required_permission' => $row['required_permission'],
            ], $request->user());
        }

        return $this->index($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WorkflowStage $stage): array
    {
        return [
            'id' => $stage->id,
            'entity_type' => $stage->entity_type,
            'sequence' => $stage->sequence,
            'code' => $stage->code,
            'name' => $stage->name,
            'applies_to' => $stage->applies_to,
            'sla_days' => $stage->sla_days,
            'is_active' => $stage->is_active,
            'is_skippable' => $stage->is_skippable,
            'required_permission' => $stage->required_permission,
        ];
    }
}
