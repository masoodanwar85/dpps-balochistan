<?php

namespace App\Services\Applications;

use App\Models\ApplicationPenalty;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationPenalties
{
    public function __construct(
        private ActivityLogger $logger,
        private ApplicationFees $fees,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(LicenseApplication $application, array $data, User $actor): ApplicationPenalty
    {
        $this->assertOpen($application);
        $guidance = $this->fees->guidance($application);
        $type = (string) $data['penalty_type'];
        $rate = $guidance['rates'][$type]['amount'] ?? null;
        $helper = collect($guidance['helpers'])->firstWhere('penalty_type', $type);
        $standard = $data['standard_amount'] ?? null;
        $reduced = $standard !== null && bccomp((string) $data['final_amount'], (string) $standard, 2) === -1;

        if ($reduced && mb_strlen(trim((string) ($data['waiver_reason'] ?? ''))) < 3) {
            throw ValidationException::withMessages([
                'waiver_reason' => ['Enter the waiver reason.'],
            ]);
        }

        return DB::transaction(function () use ($application, $data, $actor, $rate, $helper, $reduced) {
            $penalty = ApplicationPenalty::query()->create([
                'application_id' => $application->id,
                'penalty_type' => $data['penalty_type'],
                'reference_rate' => $rate,
                'helper_info' => is_array($helper) ? $helper['text'] : null,
                'basis' => $data['basis'],
                'standard_amount' => $data['standard_amount'] ?? null,
                'final_amount' => $data['final_amount'],
                'waiver_reason' => $reduced ? $data['waiver_reason'] : null,
                'waiver_order_no' => $reduced ? ($data['waiver_order_no'] ?? null) : null,
                'entered_by' => $actor->id,
                'entered_at' => now(),
            ]);

            $this->fees->refreshTotals($application);
            $this->logger->log(
                'penalty_entered',
                'Entered a '.$this->label((string) $data['penalty_type']).' penalty',
                'application_penalty',
                $penalty->id,
                $actor,
                null,
                [
                    'final_amount' => $penalty->final_amount,
                    'standard_amount' => $penalty->standard_amount,
                ],
            );

            return $penalty;
        });
    }

    public function approve(LicenseApplication $application, ApplicationPenalty $penalty, User $actor): void
    {
        if ($penalty->application_id !== $application->id) {
            abort(404);
        }

        $this->assertOpen($application);

        if (! $this->reduced($penalty)) {
            throw ValidationException::withMessages([
                'penalty' => ['This penalty does not need a waiver.'],
            ]);
        }

        if ($penalty->waiver_approved_by !== null) {
            throw ValidationException::withMessages([
                'penalty' => ['This penalty is already approved.'],
            ]);
        }

        $penalty->waiver_approved_by = $actor->id;
        $penalty->save();
        $this->logger->log(
            'penalty_waived',
            'Approved a reduced penalty',
            'application_penalty',
            $penalty->id,
            $actor,
            null,
            [
                'final_amount' => $penalty->final_amount,
                'standard_amount' => $penalty->standard_amount,
                'waiver_reason' => $penalty->waiver_reason,
            ],
        );
    }

    public function hasPendingWaiver(LicenseApplication $application): bool
    {
        return $application->penalties()
            ->whereNotNull('standard_amount')
            ->whereColumn('final_amount', '<', 'standard_amount')
            ->whereNull('waiver_approved_by')
            ->exists();
    }

    private function reduced(ApplicationPenalty $penalty): bool
    {
        return $penalty->standard_amount !== null
            && bccomp((string) $penalty->final_amount, (string) $penalty->standard_amount, 2) === -1;
    }

    private function assertOpen(LicenseApplication $application): void
    {
        if (! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }
    }

    private function label(string $type): string
    {
        return match ($type) {
            'late_renewal' => 'late renewal',
            'no_technical_staff' => 'no technical staff',
            'restoration' => 'restoration',
            default => 'other',
        };
    }
}
