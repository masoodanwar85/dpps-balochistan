<?php

namespace App\Services\Applications;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationWriter
{
    public function __construct(
        private ActivityLogger $logger,
        private ApplicationNumber $numbers,
        private ApplicationStages $stages,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): LicenseApplication
    {
        $type = (string) $data['licensable_type'];
        $id = (int) $data['licensable_id'];
        $kind = (string) $data['application_type'];
        $this->owner($type, $id, $actor);
        $license = $this->currentLicense($type, $id);

        if ($kind === 'renewal') {
            $this->assertRenewalWindow($license);
        }

        $template = ChecklistTemplate::query()
            ->where('entity_type', $type)
            ->where('application_type', $kind)
            ->where('status', 'published')
            ->first();

        if (! $template instanceof ChecklistTemplate) {
            throw ValidationException::withMessages([
                'checklist_template_id' => ['No published checklist for this application.'],
            ]);
        }

        $this->assertNoOpenApplication($type, $id);

        return DB::transaction(function () use ($data, $actor, $type, $id, $kind, $license, $template) {
            $this->assertNoOpenApplication($type, $id);
            $party = $type === 'company' ? 'C' : 'D';
            $receivedAt = $data['received_at'] ?? null;

            $application = LicenseApplication::query()->create([
                'application_no' => $this->numbers->next($party),
                'licensable_type' => $type,
                'licensable_id' => $id,
                'application_type' => $kind,
                'checklist_template_id' => $template->id,
                'previous_license_id' => $kind === 'renewal' ? $license?->id : null,
                'submitted_via' => 'office',
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
                'diary_no' => $data['diary_no'] ?? null,
                'received_by' => $receivedAt ? $actor->id : null,
                'received_at' => $receivedAt,
                'status' => 'submitted',
                'late_days' => $this->lateDays($kind, $license),
                'fee_amount' => 0,
                'penalty_total' => 0,
                'total_payable' => 0,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($template->items()->orderBy('sort_order')->get() as $item) {
                $application->checklistItems()->create([
                    'checklist_item_id' => $item->id,
                    'title_snapshot' => $item->title,
                    'annex_code_snapshot' => $item->annex_code,
                    'status' => 'pending',
                ]);
            }

            $this->stages->start($application, $actor);
            $application = $application->refresh();

            $this->logger->log(
                'created',
                'Created application '.$application->application_no.' for '.$application->applicantName(),
                'license_application',
                $application->id,
                $actor,
                null,
                [
                    'application_no' => $application->application_no,
                    'application_type' => $application->application_type,
                    'licensable_type' => $application->licensable_type,
                    'licensable_id' => $application->licensable_id,
                ],
            );

            return $application;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LicenseApplication $application, array $data, User $actor): LicenseApplication
    {
        $this->assertOpen($application);
        $old = [
            'diary_no' => $application->diary_no,
            'received_at' => $application->received_at?->toIso8601String(),
            'total_pages' => $application->total_pages,
        ];

        if (array_key_exists('diary_no', $data)) {
            $application->diary_no = $data['diary_no'];
        }

        if (array_key_exists('received_at', $data)) {
            $application->received_at = $data['received_at'];
            $application->received_by = $data['received_at'] ? $actor->id : null;
        }

        if (array_key_exists('total_pages', $data)) {
            $application->total_pages = $data['total_pages'];
        }

        $application->updated_by = $actor->id;
        $application->save();

        $this->logger->log(
            'updated',
            'Updated application '.$application->application_no,
            'license_application',
            $application->id,
            $actor,
            $old,
            [
                'diary_no' => $application->diary_no,
                'received_at' => $application->received_at?->toIso8601String(),
                'total_pages' => $application->total_pages,
            ],
        );

        return $application->refresh();
    }

    public function reject(LicenseApplication $application, string $reason, User $actor): LicenseApplication
    {
        return $this->close($application, 'rejected', $reason, $actor, 'Rejected application '.$application->application_no);
    }

    public function withdraw(LicenseApplication $application, string $reason, User $actor): LicenseApplication
    {
        return $this->close($application, 'withdrawn', null, $actor, 'Withdrew application '.$application->application_no, $reason);
    }

    private function close(
        LicenseApplication $application,
        string $status,
        ?string $rejectionReason,
        User $actor,
        string $description,
        ?string $withdrawReason = null,
    ): LicenseApplication {
        $this->assertOpen($application);
        $application->status = $status;
        $application->rejection_reason = $rejectionReason;
        $application->updated_by = $actor->id;
        $application->save();

        $this->logger->log(
            $status === 'rejected' ? 'rejected' : 'updated',
            $description,
            'license_application',
            $application->id,
            $actor,
            null,
            ['reason' => $rejectionReason ?? $withdrawReason, 'status' => $status],
        );

        return $application->refresh();
    }

    private function owner(string $type, int $id, User $actor): Company|Dealer
    {
        if ($type === 'company') {
            $company = Company::query()->visibleTo($actor)->whereKey($id)->first();

            if (! $company instanceof Company) {
                throw ValidationException::withMessages([
                    'licensable_id' => ['Choose a company.'],
                ]);
            }

            return $company;
        }

        $dealer = Dealer::query()->visibleTo($actor)->whereKey($id)->first();

        if (! $dealer instanceof Dealer) {
            throw ValidationException::withMessages([
                'licensable_id' => ['Choose a dealer.'],
            ]);
        }

        return $dealer;
    }

    private function currentLicense(string $type, int $id): ?License
    {
        $license = License::query()
            ->where('licensable_type', $type)
            ->where('licensable_id', $id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->first();

        return $license instanceof License ? $license : null;
    }

    private function assertRenewalWindow(?License $license): void
    {
        if (! $license instanceof License) {
            throw ValidationException::withMessages([
                'application_type' => ['A renewal cannot be submitted before the renewal window opens.'],
            ]);
        }

        $days = ApplicationStages::settingDays('renewal_window_days', 60);
        $opensOn = $license->valid_to->copy()->startOfDay()->subDays($days);

        if (now()->startOfDay()->lt($opensOn)) {
            throw ValidationException::withMessages([
                'application_type' => ['A renewal cannot be submitted before the renewal window opens.'],
            ]);
        }
    }

    private function lateDays(string $kind, ?License $license): int
    {
        if ($kind !== 'renewal' || ! $license instanceof License) {
            return 0;
        }

        $expiry = $license->valid_to->copy()->startOfDay();

        if (now()->startOfDay()->lte($expiry)) {
            return 0;
        }

        return (int) $expiry->diffInDays(now()->startOfDay());
    }

    private function assertNoOpenApplication(string $type, int $id): void
    {
        $open = LicenseApplication::withTrashed()
            ->where('licensable_type', $type)
            ->where('licensable_id', $id)
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn'])
            ->exists();

        if ($open) {
            $label = $type === 'company' ? 'company' : 'dealer';

            throw ValidationException::withMessages([
                'licensable_id' => ["This {$label} already has an open application."],
            ]);
        }
    }

    private function assertOpen(LicenseApplication $application): void
    {
        if (! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }
    }
}
