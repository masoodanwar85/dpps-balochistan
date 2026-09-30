<?php

namespace App\Services\Applications;

use App\Models\Challan;
use App\Models\ChallanItem;
use App\Models\DocumentType;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\DocumentWarningException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationChallans
{
    public function __construct(
        private ActivityLogger $logger,
        private DocumentStore $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(LicenseApplication $application, array $data, User $actor, ?UploadedFile $file): Challan
    {
        if (! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }

        if ($file instanceof UploadedFile && ! $actor->can('documents.upload')) {
            abort(403, 'You cannot upload this file.');
        }

        $documentData = $file instanceof UploadedFile ? $this->documentData($application, $data) : null;

        try {
            $challan = DB::transaction(function () use ($application, $data, $actor) {
                $challan = Challan::query()->create([
                    'application_id' => $application->id,
                    'challan_no' => $data['challan_no'],
                    'bank_name' => $data['bank_name'],
                    'branch' => $data['branch'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'amount' => $data['amount'],
                    'verification_status' => 'pending',
                ]);
                ChallanItem::query()->create([
                    'challan_id' => $challan->id,
                    'item_type' => $this->itemType($application),
                    'amount' => $data['amount'],
                ]);
                $this->logger->log(
                    'created',
                    'Recorded challan '.$challan->challan_no,
                    'challan',
                    $challan->id,
                    $actor,
                );

                return $challan;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'challan_no' => ['This challan number is already used.'],
            ]);
        }

        if (! $file instanceof UploadedFile) {
            return $challan;
        }

        try {
            $document = $this->documents->createForChallan($challan->id, $file, $documentData ?? [], $actor);
        } catch (DocumentWarningException $exception) {
            $challan->items()->delete();
            $challan->delete();

            throw $exception;
        } catch (ValidationException $exception) {
            $challan->items()->delete();
            $challan->delete();

            throw $exception;
        }

        $challan->document_id = $document->id;
        $challan->save();

        return $challan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function verify(Challan $challan, array $data, User $actor): void
    {
        $application = $challan->application;

        if (! $application instanceof LicenseApplication || ! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }

        if ($challan->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'verification_status' => ['This challan has already been decided.'],
            ]);
        }

        $status = (string) $data['verification_status'];

        if ($status === 'rejected' && mb_strlen(trim((string) ($data['remarks'] ?? ''))) < 3) {
            throw ValidationException::withMessages([
                'remarks' => ['Enter the rejection remarks.'],
            ]);
        }

        $challan->fill([
            'verification_status' => $status,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);
        $challan->save();
        $this->logger->log(
            $status === 'verified' ? 'verified' : 'rejected',
            ($status === 'verified' ? 'Verified challan ' : 'Rejected challan ').$challan->challan_no,
            'challan',
            $challan->id,
            $actor,
            null,
            ['remarks' => $challan->remarks],
        );
    }

    private function itemType(LicenseApplication $application): string
    {
        return match ($application->application_type) {
            'renewal' => 'renewal_fee',
            'restoration' => 'other',
            default => 'registration_fee',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function documentData(LicenseApplication $application, array $data): array
    {
        $type = DocumentType::query()
            ->whereKey($data['document_type_id'] ?? 0)
            ->where('is_active', true)
            ->whereIn('applies_to', [$application->licensable_type, 'any'])
            ->first();

        if (! $type instanceof DocumentType) {
            throw ValidationException::withMessages([
                'document_type_id' => ['Choose a document type for this applicant.'],
            ]);
        }

        return [
            'document_type_id' => $type->id,
            'title' => $data['title'] ?? 'Treasury challan',
            'attested_by' => 'none',
            'confirm_warnings' => $data['confirm_warnings'] ?? false,
            'warning_reason' => $data['warning_reason'] ?? null,
        ];
    }
}
