<?php

namespace App\Services\Applications;

use App\Models\ApplicationChecklistItem;
use App\Models\ApplicationPenalty;
use App\Models\ApplicationStageLog;
use App\Models\Challan;
use App\Models\Dealer;
use App\Models\DeficiencyLetter;
use App\Models\Document;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\WorkflowStage;
use App\Services\Documents\DocumentStore;
use App\Services\Licenses\LicenseReadiness;

class ApplicationPresenter
{
    public function __construct(
        private ApplicationStages $stages,
        private DocumentStore $documents,
        private ApplicationFees $fees,
        private LicenseReadiness $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(LicenseApplication $application): array
    {
        $this->stages->refreshSla($application);
        $application->loadMissing('currentStage');
        $log = $application->stageLogs()->whereNull('completed_at')->latest('id')->first();
        $stage = $application->currentStage;
        $days = 0;
        $sla = null;
        $breached = false;

        if ($log instanceof ApplicationStageLog) {
            $days = (int) $log->entered_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
            $breached = (bool) $log->fresh()?->sla_breached;
        }

        if ($stage instanceof WorkflowStage) {
            $sla = $stage->sla_days;
        }

        return [
            'id' => $application->id,
            'application_no' => $application->application_no,
            'applicant_name' => $application->applicantName(),
            'licensable_type' => $application->licensable_type,
            'licensable_id' => $application->licensable_id,
            'application_type' => $application->application_type,
            'status' => $application->status,
            'submitted_via' => $application->submitted_via,
            'district_name' => $this->districtName($application),
            'current_stage' => $stage instanceof WorkflowStage ? [
                'id' => $stage->id,
                'code' => $stage->code,
                'sequence' => $stage->sequence,
                'name' => $stage->name,
                'sla_days' => $stage->sla_days,
                'is_skippable' => $stage->is_skippable,
                'required_permission' => $stage->required_permission,
            ] : null,
            'days_in_stage' => $days,
            'sla_days' => $sla,
            'sla_breached' => $breached,
            'stage_due_at' => $sla === null || ! $log instanceof ApplicationStageLog ? null : $log->due_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(LicenseApplication $application): array
    {
        $this->readiness->refresh($application);
        $application->load(['template', 'checklistItems.source', 'letters', 'stageLogs.stage']);
        $summary = $this->summary($application);
        $logs = $application->stageLogs->keyBy('stage_id');
        $done = 0;
        $items = [];

        foreach ($application->checklistItems as $item) {
            if (in_array($item->status, ['verified', 'not_applicable'], true)) {
                $done++;
            }

            $items[] = $this->item($item);
        }

        $rail = [];

        foreach ($this->stages->stages($application) as $stage) {
            $log = $logs->get($stage->id);
            $rail[] = [
                'id' => $stage->id,
                'sequence' => $stage->sequence,
                'code' => $stage->code,
                'name' => $stage->name,
                'is_active' => $stage->is_active,
                'is_skippable' => $stage->is_skippable,
                'required_permission' => $stage->required_permission,
                'state' => $this->railState($application, $stage, $log instanceof ApplicationStageLog ? $log : null),
            ];
        }

        return [
            'application' => array_merge($summary, [
                'diary_no' => $application->diary_no,
                'received_at' => $application->received_at?->toDateString(),
                'total_pages' => $application->total_pages,
                'late_days' => $application->late_days,
                'fee_amount' => $application->fee_amount,
                'penalty_total' => $application->penalty_total,
                'total_payable' => $application->total_payable,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'rejection_reason' => $application->rejection_reason,
                'checklist_name' => $application->template?->name,
                'checklist_version' => $application->template?->version_no,
            ]),
            'stages' => $rail,
            'checklist' => [
                'done' => $done,
                'total' => count($items),
                'items' => $items,
            ],
            'letters' => $application->letters->map(fn (DeficiencyLetter $letter) => [
                'id' => $letter->id,
                'letter_no' => $letter->letter_no,
                'issued_at' => $letter->issued_at?->toDateString(),
                'reply_due_date' => $letter->reply_due_date?->toDateString(),
                'response_received_at' => $letter->response_received_at?->toDateString(),
                'status' => $letter->status,
            ])->values()->all(),
            'fees' => $this->fees($application),
            'issuance' => $this->issuance($application),
            'document_types' => $this->documents->typesFor($application->licensable_type),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function issuance(LicenseApplication $application): ?array
    {
        $application->loadMissing('currentStage');

        if ($application->status === 'issued') {
            $license = License::query()->where('application_id', $application->id)->orderByDesc('id')->first();

            if (! $license instanceof License) {
                return null;
            }

            return [
                'blockers' => [],
                'warnings' => [],
                'license_no' => $license->license_no,
                'license_id' => $license->id,
                'valid_from' => $license->valid_from?->toDateString(),
                'valid_to' => $license->valid_to?->toDateString(),
                'documents_status' => $license->documents_status,
                'enforcement' => (bool) $license->issued_with_enforcement,
                'outstanding_required' => $this->readiness->outstandingRequired($application),
                'required_total' => $application->checklistItems()->count(),
            ];
        }

        if ($application->currentStage?->code !== 'issuance' || ! $application->isOpen()) {
            return null;
        }

        return $this->readiness->assessment($application);
    }

    /**
     * @return array<string, mixed>
     */
    private function fees(LicenseApplication $application): array
    {
        $quote = $this->fees->quote($application);
        $guidance = $this->fees->guidance($application);
        $challans = $application->challans()->with('document')->orderBy('id')->get();
        $paid = (float) $challans->where('verification_status', 'verified')->sum(fn (Challan $challan) => (float) $challan->amount);

        return [
            'quote' => $quote,
            'recorded' => (float) $application->fee_amount > 0,
            'penalty_total' => $application->penalty_total,
            'total_payable' => $application->total_payable,
            'rates' => $guidance['rates'],
            'helpers' => $guidance['helpers'],
            'penalties' => $application->penalties()->with('approver')->orderBy('id')->get()->map(fn (ApplicationPenalty $penalty) => [
                'id' => $penalty->id,
                'penalty_type' => $penalty->penalty_type,
                'reference_rate' => $penalty->reference_rate,
                'helper_info' => $penalty->helper_info,
                'basis' => $penalty->basis,
                'standard_amount' => $penalty->standard_amount,
                'final_amount' => $penalty->final_amount,
                'is_waived_or_reduced' => $penalty->standard_amount !== null
                    && bccomp((string) $penalty->final_amount, (string) $penalty->standard_amount, 2) === -1,
                'waiver_reason' => $penalty->waiver_reason,
                'waiver_order_no' => $penalty->waiver_order_no,
                'waiver_approved' => $penalty->waiver_approved_by !== null,
                'waiver_approved_by' => $penalty->approver?->name,
            ])->values()->all(),
            'challans' => $challans->map(fn (Challan $challan) => [
                'id' => $challan->id,
                'challan_no' => $challan->challan_no,
                'bank_name' => $challan->bank_name,
                'branch' => $challan->branch,
                'payment_date' => $challan->payment_date?->toDateString(),
                'amount' => $challan->amount,
                'verification_status' => $challan->verification_status,
                'remarks' => $challan->remarks,
                'document' => $challan->document instanceof Document ? $this->documents->present($challan->document) : null,
            ])->values()->all(),
            'paid_verified' => number_format($paid, 2, '.', ''),
            'balance' => number_format((float) $application->total_payable - $paid, 2, '.', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(ApplicationChecklistItem $item): array
    {
        $files = Document::query()
            ->where('documentable_type', 'application_checklist_item')
            ->where('documentable_id', $item->id)
            ->whereNull('replaced_by_id')
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document) => $this->documents->present($document))
            ->values()
            ->all();

        return [
            'id' => $item->id,
            'annex_code' => $item->annex_code_snapshot,
            'title' => $item->title_snapshot,
            'status' => $item->status,
            'page_count' => $item->page_count,
            'officer_remarks' => $item->officer_remarks,
            'requires_validity_dates' => (bool) $item->source?->requires_validity_dates,
            'attestation_required' => $item->source?->attestation_required ?? 'none',
            'max_files' => $item->source?->max_files ?? 1,
            'files' => $files,
        ];
    }

    private function railState(LicenseApplication $application, WorkflowStage $stage, ?ApplicationStageLog $log): string
    {
        if ($application->current_stage_id === $stage->id && ($log === null || $log->completed_at === null)) {
            return 'current';
        }

        if ($log instanceof ApplicationStageLog && $log->outcome === 'skipped') {
            return 'skipped';
        }

        if ($log instanceof ApplicationStageLog && $log->outcome === 'completed') {
            return 'done';
        }

        if (! $stage->is_active) {
            return 'inactive';
        }

        return 'upcoming';
    }

    private function districtName(LicenseApplication $application): ?string
    {
        if ($application->licensable_type !== 'dealer') {
            return null;
        }

        return Dealer::withTrashed()->find($application->licensable_id)?->district?->name;
    }
}
