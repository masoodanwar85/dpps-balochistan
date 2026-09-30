<?php

namespace App\Services\Applications;

use App\Models\ApplicationChecklistItem;
use App\Models\DeficiencyLetter;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\ActivityLogger;
use App\Services\Documents\DocumentStore;
use App\Services\Notifications\InAppNotifications;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeficiencyLetters
{
    public function __construct(
        private ActivityLogger $logger,
        private ApplicationNumber $numbers,
        private ApplicationStages $stages,
        private InAppNotifications $notifications,
    ) {}

    public function generate(LicenseApplication $application, User $actor): DeficiencyLetter
    {
        $application->loadMissing('currentStage');
        $stage = $application->currentStage;

        if (! $stage instanceof WorkflowStage || $stage->code !== 'deficiency') {
            throw ValidationException::withMessages([
                'stage' => ['Deficiency letters are issued during the deficiency stage.'],
            ]);
        }

        if (! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }

        $items = $application->checklistItems()->where('status', 'deficient')->orderBy('annex_code_snapshot')->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Mark the missing items as deficient first.'],
            ]);
        }

        $issued = now()->startOfDay();
        $due = $issued->copy()->addDays(ApplicationStages::settingDays('deficiency_reply_days', 15));
        $letterNo = $this->numbers->letterNext();
        $path = 'deficiency-letters/'.$letterNo.'.pdf';

        $binary = Pdf::loadView('pdfs.deficiency-letter', [
            'letterNo' => $letterNo,
            'issuedOn' => $issued->format('d-m-Y'),
            'replyDue' => $due->format('d-m-Y'),
            'applicationNo' => $application->application_no,
            'applicant' => $application->applicantName(),
            'applicationType' => $application->application_type,
            'items' => $items->map(fn (ApplicationChecklistItem $item) => [
                'annex' => $item->annex_code_snapshot,
                'title' => $item->title_snapshot,
                'remarks' => $item->officer_remarks ?: 'Deficient',
            ])->all(),
        ])->output();

        if (Storage::disk(DocumentStore::DISK)->put($path, $binary) === false) {
            throw ValidationException::withMessages([
                'pdf' => ['The letter could not be stored.'],
            ]);
        }

        try {
            $letter = DB::transaction(function () use ($application, $actor, $items, $issued, $due, $letterNo, $path) {
                $letter = DeficiencyLetter::query()->create([
                    'application_id' => $application->id,
                    'letter_no' => $letterNo,
                    'issued_at' => $issued->toDateString(),
                    'reply_due_date' => $due->toDateString(),
                    'status' => 'open',
                    'pdf_path' => $path,
                ]);

                foreach ($items as $item) {
                    $letter->items()->create([
                        'application_checklist_item_id' => $item->id,
                        'remarks' => $item->officer_remarks ?: 'Deficient',
                    ]);
                }

                $this->stages->syncDeficiencyStatus($application->refresh());

                $this->logger->log(
                    'created',
                    'Issued deficiency letter '.$letter->letter_no,
                    'deficiency_letter',
                    $letter->id,
                    $actor,
                    null,
                    ['letter_no' => $letter->letter_no, 'application_id' => $application->id],
                );
                $this->notifications->deficiencyIssued($application, $letter->letter_no);

                return $letter;
            });
        } catch (\Throwable $exception) {
            Storage::disk(DocumentStore::DISK)->delete($path);

            throw $exception;
        }

        return $letter->refresh();
    }

    public function resolve(DeficiencyLetter $letter, User $actor): DeficiencyLetter
    {
        if ($letter->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['This letter is already closed.'],
            ]);
        }

        $letter->status = 'resolved';
        $letter->response_received_at = now()->toDateString();
        $letter->save();

        $application = $letter->application;

        if ($application instanceof LicenseApplication) {
            $this->stages->syncDeficiencyStatus($application);
        }

        $this->logger->log(
            'updated',
            'Resolved deficiency letter '.$letter->letter_no,
            'deficiency_letter',
            $letter->id,
            $actor,
            ['status' => 'open'],
            ['status' => 'resolved'],
        );

        return $letter->refresh();
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function downloadUrl(DeficiencyLetter $letter, User $actor): array
    {
        if ($letter->pdf_path === null || ! Storage::disk(DocumentStore::DISK)->exists($letter->pdf_path)) {
            throw ValidationException::withMessages([
                'pdf' => ['The letter file is missing.'],
            ]);
        }

        $expires = now()->addMinutes(5);
        $url = Storage::disk(DocumentStore::DISK)->temporaryUrl($letter->pdf_path, $expires);

        $this->logger->log(
            'downloaded',
            'Opened deficiency letter '.$letter->letter_no,
            'deficiency_letter',
            $letter->id,
            $actor,
        );

        return [
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
        ];
    }
}
