<?php

namespace Database\Seeders;

use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;

class WorkflowStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            [1, 'submission', 'Application submitted (letterhead / email / portal)', 'both', null, true, false, 'applications.create'],
            [2, 'progress_review', 'Review of last period\'s progress', 'renewal', 3, true, false, 'applications.process'],
            [3, 'file_review', 'File submission and checklist review', 'both', 15, true, false, 'applications.process'],
            [4, 'deficiency', 'Deficiency letter (if any)', 'both', 4, true, true, 'applications.process'],
            [5, 'fee', 'Fee and penalty deposit (challan)', 'both', 10, true, false, 'challans.verify'],
            [6, 'higher_approval', 'Approval by Secretary / DG', 'both', 3, false, false, 'licenses.issue'],
            [7, 'issuance', 'Issuance of license certificate', 'both', 3, true, false, 'licenses.issue'],
        ];

        foreach (['company', 'dealer'] as $entityType) {
            foreach ($stages as [$sequence, $code, $name, $appliesTo, $slaDays, $active, $skippable, $permission]) {
                WorkflowStage::query()->updateOrCreate(
                    [
                        'entity_type' => $entityType,
                        'sequence' => $sequence,
                    ],
                    [
                        'code' => $code,
                        'name' => $name,
                        'description' => $name,
                        'applies_to' => $appliesTo,
                        'sla_days' => $slaDays,
                        'is_active' => $active,
                        'is_skippable' => $skippable,
                        'required_permission' => $permission,
                    ],
                );
            }
        }
    }
}
