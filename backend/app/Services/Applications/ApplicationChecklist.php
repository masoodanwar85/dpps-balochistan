<?php

namespace App\Services\Applications;

use App\Models\ApplicationChecklistItem;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\DocumentWarningException;
use App\Services\Licenses\LicenseDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ApplicationChecklist
{
    public function __construct(
        private ActivityLogger $logger,
        private DocumentStore $documents,
        private LicenseDocuments $licenseDocuments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LicenseApplication $application, ApplicationChecklistItem $item, array $data, User $actor): ApplicationChecklistItem
    {
        $this->assertItem($application, $item);
        $this->assertMutable($application);
        $this->licenseDocuments->assertOpenPeriod($application);

        $status = (string) $data['status'];

        if ($status === 'deficient' && trim((string) ($data['officer_remarks'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'officer_remarks' => ['Enter the deficiency remarks.'],
            ]);
        }

        $old = $item->status;
        $item->status = $status;
        $item->officer_remarks = $data['officer_remarks'] ?? null;
        $item->page_count = $data['page_count'] ?? $item->page_count;
        $item->verified_by = $status === 'verified' ? $actor->id : null;
        $item->verified_at = $status === 'verified' ? now() : null;
        $item->save();

        $this->logger->log(
            $status === 'verified' ? 'approved' : 'updated',
            ucfirst(str_replace('_', ' ', $status)).' '.$item->annex_code_snapshot.' '.$item->title_snapshot,
            'application_checklist_item',
            $item->id,
            $actor,
            ['status' => $old],
            ['status' => $status, 'officer_remarks' => $item->officer_remarks, 'page_count' => $item->page_count],
        );
        $this->licenseDocuments->sync($application, $actor);

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(LicenseApplication $application, ApplicationChecklistItem $item, UploadedFile $file, array $data, User $actor): Document
    {
        $this->assertItem($application, $item);
        $this->assertMutable($application);
        $this->licenseDocuments->assertOpenPeriod($application);
        $item->loadMissing('source');
        $source = $item->source;

        if ($source && $source->requires_validity_dates && (empty($data['issue_date']) || empty($data['expiry_date']))) {
            throw ValidationException::withMessages([
                'expiry_date' => ['Enter the issue date and expiry date for this item.'],
            ]);
        }

        $current = Document::query()
            ->where('documentable_type', 'application_checklist_item')
            ->where('documentable_id', $item->id)
            ->whereNull('replaced_by_id')
            ->count();
        $max = $source?->max_files ?? 1;

        if ($current >= $max) {
            throw ValidationException::withMessages([
                'file' => ['This item already has the maximum number of files.'],
            ]);
        }

        $type = DocumentType::query()
            ->whereKey($data['document_type_id'])
            ->where('is_active', true)
            ->whereIn('applies_to', [$application->licensable_type, 'any'])
            ->first();

        if (! $type instanceof DocumentType) {
            throw ValidationException::withMessages([
                'document_type_id' => ['Choose a document type for this applicant.'],
            ]);
        }

        try {
            $document = $this->documents->createForChecklistItem($item->id, $file, [
                'document_type_id' => $type->id,
                'title' => $data['title'] ?: $item->title_snapshot,
                'issue_date' => $data['issue_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'attested_by' => $source?->attestation_required ?: 'none',
                'confirm_warnings' => $data['confirm_warnings'] ?? false,
                'warning_reason' => $data['warning_reason'] ?? null,
            ], $actor);
        } catch (DocumentWarningException $exception) {
            throw $exception;
        }

        if (in_array($item->status, ['pending', 'deficient'], true)) {
            $item->status = 'submitted';
            $item->verified_by = null;
            $item->verified_at = null;
            $item->save();
        }

        return $document;
    }

    private function assertItem(LicenseApplication $application, ApplicationChecklistItem $item): void
    {
        if ($item->application_id !== $application->id) {
            abort(404);
        }
    }

    private function assertMutable(LicenseApplication $application): void
    {
        if (in_array($application->status, ['rejected', 'withdrawn'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }
    }
}
