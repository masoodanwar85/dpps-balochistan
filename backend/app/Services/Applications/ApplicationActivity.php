<?php

namespace App\Services\Applications;

use App\Models\ActivityLog;
use App\Models\ApplicationChecklistItem;
use App\Models\ApplicationPenalty;
use App\Models\Challan;
use App\Models\DeficiencyLetter;
use App\Models\Document;
use App\Models\LicenseApplication;
use Illuminate\Support\Collection;

class ApplicationActivity
{
    /**
     * @return Collection<int, ActivityLog>
     */
    public function forApplication(LicenseApplication $application): Collection
    {
        $itemIds = ApplicationChecklistItem::query()
            ->where('application_id', $application->id)
            ->pluck('id');
        $letterIds = DeficiencyLetter::query()
            ->where('application_id', $application->id)
            ->pluck('id');
        $penaltyIds = ApplicationPenalty::query()
            ->where('application_id', $application->id)
            ->pluck('id');
        $challanIds = Challan::query()
            ->where('application_id', $application->id)
            ->pluck('id');
        $documentIds = Document::withTrashed()
            ->where(function ($query) use ($itemIds, $challanIds) {
                $query->where(function ($inner) use ($itemIds) {
                    $inner->where('documentable_type', 'application_checklist_item')
                        ->whereIn('documentable_id', $itemIds->all() === [] ? [0] : $itemIds->all());
                })->orWhere(function ($inner) use ($challanIds) {
                    $inner->where('documentable_type', 'challan')
                        ->whereIn('documentable_id', $challanIds->all() === [] ? [0] : $challanIds->all());
                });
            })
            ->pluck('id');

        return ActivityLog::query()
            ->with('user')
            ->where(function ($query) use ($application, $itemIds, $letterIds, $penaltyIds, $challanIds, $documentIds) {
                $query->where(function ($inner) use ($application) {
                    $inner->where('subject_type', 'license_application')->where('subject_id', $application->id);
                })->orWhere(function ($inner) use ($itemIds) {
                    $inner->where('subject_type', 'application_checklist_item')
                        ->whereIn('subject_id', $itemIds->all() === [] ? [0] : $itemIds->all());
                })->orWhere(function ($inner) use ($letterIds) {
                    $inner->where('subject_type', 'deficiency_letter')
                        ->whereIn('subject_id', $letterIds->all() === [] ? [0] : $letterIds->all());
                })->orWhere(function ($inner) use ($penaltyIds) {
                    $inner->where('subject_type', 'application_penalty')
                        ->whereIn('subject_id', $penaltyIds->all() === [] ? [0] : $penaltyIds->all());
                })->orWhere(function ($inner) use ($challanIds) {
                    $inner->where('subject_type', 'challan')
                        ->whereIn('subject_id', $challanIds->all() === [] ? [0] : $challanIds->all());
                })->orWhere(function ($inner) use ($documentIds) {
                    $inner->where('subject_type', 'document')
                        ->whereIn('subject_id', $documentIds->all() === [] ? [0] : $documentIds->all());
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'user_name' => $log->user?->name,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
