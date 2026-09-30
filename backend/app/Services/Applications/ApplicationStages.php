<?php

namespace App\Services\Applications;

use App\Models\ApplicationStageLog;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\ActivityLogger;
use App\Support\SettingValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationStages
{
    public function __construct(
        private ActivityLogger $logger,
        private ApplicationFees $fees,
        private ApplicationPenalties $penalties,
    ) {}

    public function start(LicenseApplication $application, User $actor): void
    {
        $stage = $this->stages($application)->first();

        if (! $stage instanceof WorkflowStage) {
            throw ValidationException::withMessages([
                'current_stage_id' => ['Workflow stages are not configured.'],
            ]);
        }

        $this->enter($application, $stage, $actor, false);
    }

    public function complete(LicenseApplication $application, WorkflowStage $stage, User $actor, ?string $remarks): LicenseApplication
    {
        return DB::transaction(function () use ($application, $stage, $actor, $remarks) {
            $application = $this->locked($application);
            $this->assertCurrent($application, $stage);
            $this->assertOpen($application);
            $this->assertPermission($actor, $stage, 'You cannot complete this stage.');

            if ($stage->code === 'issuance') {
                throw ValidationException::withMessages([
                    'stage' => ['The license is issued from the Issue action.'],
                ]);
            }

            if ($stage->code === 'deficiency') {
                $this->assertDeficiencyCanComplete($application);
            }

            if ($stage->code === 'fee') {
                $this->applyFee($application);

                if ($this->penalties->hasPendingWaiver($application)) {
                    throw ValidationException::withMessages([
                        'stage' => ['Approve the reduced penalty before completing this stage.'],
                    ]);
                }
            }

            $this->closeOpenLog($application, $actor, 'completed', $remarks);
            $this->logger->log(
                'updated',
                'Completed stage '.$stage->name,
                'license_application',
                $application->id,
                $actor,
            );

            return $this->advance($application, $stage, $actor);
        });
    }

    public function skip(LicenseApplication $application, WorkflowStage $stage, User $actor, string $remarks): LicenseApplication
    {
        return DB::transaction(function () use ($application, $stage, $actor, $remarks) {
            $application = $this->locked($application);
            $this->assertCurrent($application, $stage);
            $this->assertOpen($application);
            $this->assertPermission($actor, $stage, 'You cannot skip this stage.');

            if (! $stage->is_skippable) {
                throw ValidationException::withMessages([
                    'stage' => ['This stage cannot be skipped.'],
                ]);
            }

            $this->closeOpenLog($application, $actor, 'skipped', $remarks);
            $this->logger->log(
                'updated',
                'Skipped stage '.$stage->name,
                'license_application',
                $application->id,
                $actor,
                null,
                ['remarks' => $remarks],
            );

            return $this->advance($application, $stage, $actor);
        });
    }

    public function refreshSla(LicenseApplication $application): void
    {
        $log = $application->stageLogs()->whereNull('completed_at')->latest('id')->first();

        if (! $log instanceof ApplicationStageLog || $log->sla_breached) {
            return;
        }

        $stage = $log->stage;

        if (! $stage instanceof WorkflowStage || $stage->sla_days === null) {
            return;
        }

        if (now()->greaterThan($log->due_at)) {
            $log->sla_breached = true;
            $log->save();
        }
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    public function stages(LicenseApplication $application): Collection
    {
        return WorkflowStage::query()
            ->where('entity_type', $application->licensable_type)
            ->orderBy('sequence')
            ->get();
    }

    private function advance(LicenseApplication $application, WorkflowStage $from, User $actor): LicenseApplication
    {
        $upcoming = $this->stages($application)->filter(
            fn (WorkflowStage $stage) => $stage->sequence > $from->sequence,
        );

        foreach ($upcoming as $stage) {
            if (! $stage->is_active) {
                $this->recordAutomaticSkip($application, $stage, 'Inactive stage skipped.');
                $this->logger->log(
                    'updated',
                    'Skipped inactive stage '.$stage->name,
                    'license_application',
                    $application->id,
                    $actor,
                );

                continue;
            }

            if (! $this->applies($stage, $application)) {
                $kind = $application->application_type === 'renewal' ? 'renewal' : 'new application';
                $this->recordAutomaticSkip($application, $stage, 'This stage does not apply to a '.$kind.'.');
                $this->logger->log(
                    'updated',
                    'Skipped stage '.$stage->name,
                    'license_application',
                    $application->id,
                    $actor,
                );

                continue;
            }

            $this->enter($application, $stage, $actor, true);

            return $application->refresh();
        }

        return $application->refresh();
    }

    private function enter(LicenseApplication $application, WorkflowStage $stage, User $actor, bool $applyFee): void
    {
        $entered = now();
        $due = $stage->sla_days === null ? $entered->copy() : $entered->copy()->addDays((int) $stage->sla_days);

        ApplicationStageLog::query()->create([
            'application_id' => $application->id,
            'stage_id' => $stage->id,
            'entered_at' => $entered,
            'due_at' => $due,
            'sla_breached' => false,
        ]);

        $application->current_stage_id = $stage->id;
        $application->status = $this->statusFor($application, $stage);
        $application->updated_by = $actor->id;

        if ($applyFee && $stage->code === 'fee') {
            $quote = $this->fees->quote($application);

            if ($quote !== null) {
                $application->fee_amount = $quote['amount'];
                $application->total_payable = (float) $quote['amount'] + (float) $application->penalty_total;
            }
        }

        $application->save();
    }

    private function recordAutomaticSkip(LicenseApplication $application, WorkflowStage $stage, string $remarks): void
    {
        $now = now();

        ApplicationStageLog::query()->create([
            'application_id' => $application->id,
            'stage_id' => $stage->id,
            'entered_at' => $now,
            'due_at' => $now,
            'completed_at' => $now,
            'outcome' => 'skipped',
            'remarks' => $remarks,
            'sla_breached' => false,
        ]);
    }

    private function closeOpenLog(LicenseApplication $application, User $actor, string $outcome, ?string $remarks): void
    {
        $log = $application->stageLogs()->whereNull('completed_at')->latest('id')->first();

        if (! $log instanceof ApplicationStageLog) {
            throw ValidationException::withMessages([
                'stage' => ['This stage is not open.'],
            ]);
        }

        $log->fill([
            'completed_at' => now(),
            'acted_by' => $actor->id,
            'outcome' => $outcome,
            'remarks' => $remarks,
        ]);
        $log->save();
    }

    private function statusFor(LicenseApplication $application, WorkflowStage $stage): string
    {
        return match ($stage->code) {
            'submission' => 'submitted',
            'progress_review', 'file_review' => 'under_review',
            'deficiency' => $application->letters()->where('status', 'open')->exists()
                ? 'deficiency_issued'
                : 'under_review',
            'fee' => 'fee_pending',
            'issuance' => 'fee_pending',
            default => $application->status,
        };
    }

    public function syncDeficiencyStatus(LicenseApplication $application): void
    {
        $stage = $application->currentStage;

        if (! $stage instanceof WorkflowStage || $stage->code !== 'deficiency' || ! $application->isOpen()) {
            return;
        }

        $application->status = $application->letters()->where('status', 'open')->exists()
            ? 'deficiency_issued'
            : 'under_review';
        $application->save();
    }

    private function applies(WorkflowStage $stage, LicenseApplication $application): bool
    {
        return $stage->applies_to === 'both' || $stage->applies_to === $application->application_type;
    }

    private function assertCurrent(LicenseApplication $application, WorkflowStage $stage): void
    {
        if ($application->current_stage_id !== $stage->id || $stage->entity_type !== $application->licensable_type) {
            throw ValidationException::withMessages([
                'stage' => ['Stages must be completed in sequence.'],
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

    private function assertPermission(User $actor, WorkflowStage $stage, string $message): void
    {
        if (! $actor->can($stage->required_permission)) {
            abort(403, $message);
        }
    }

    private function assertDeficiencyCanComplete(LicenseApplication $application): void
    {
        if ($application->letters()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages([
                'stage' => ['Resolve the open deficiency letter before completing this stage.'],
            ]);
        }

        if ($application->checklistItems()->where('status', 'deficient')->exists()) {
            throw ValidationException::withMessages([
                'stage' => ['Deficient items are still open. Resolve them or skip this stage with a reason.'],
            ]);
        }
    }

    private function applyFee(LicenseApplication $application): void
    {
        $quote = $this->fees->quote($application);

        if ($quote === null) {
            throw ValidationException::withMessages([
                'stage' => ['Fee is not configured for this date. Contact the system administrator.'],
            ]);
        }

        $application->fee_amount = $quote['amount'];
        $application->total_payable = (float) $quote['amount'] + (float) $application->penalty_total;
        $application->save();
    }

    private function locked(LicenseApplication $application): LicenseApplication
    {
        $locked = LicenseApplication::query()->whereKey($application->id)->lockForUpdate()->first();

        return $locked instanceof LicenseApplication ? $locked : $application;
    }

    public static function settingDays(string $key, int $fallback): int
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $fallback;
        }

        $value = SettingValue::typed($setting);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
