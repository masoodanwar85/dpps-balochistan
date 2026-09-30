<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\ActivityLogger;

class WorkflowStageWriter
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WorkflowStage $stage, array $attributes, User $actor): WorkflowStage
    {
        $fields = ['applies_to', 'sla_days', 'is_active', 'is_skippable', 'required_permission'];
        $before = $stage->only($fields);
        $stage->fill($attributes);

        if ($stage->isDirty()) {
            $stage->save();
            $this->activity->log(
                'updated',
                'Updated workflow stage '.$stage->code.'.',
                'workflow_stage',
                (int) $stage->id,
                $actor,
                $before,
                $stage->only($fields),
            );
        }

        return $stage->refresh();
    }
}
