<?php

namespace App\Services\Licenses;

use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

class LicenseDocuments
{
    public function __construct(
        private ActivityLogger $logger,
        private LicenseReadiness $readiness,
    ) {}

    public function assertOpenPeriod(LicenseApplication $application): void
    {
        if ($application->status !== 'issued') {
            return;
        }

        $license = $this->license($application);

        if ($license instanceof License && $license->valid_to->copy()->startOfDay()->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'status' => ['This license period has ended.'],
            ]);
        }
    }

    public function sync(LicenseApplication $application, User $actor): void
    {
        if ($application->status !== 'issued') {
            return;
        }

        $license = $this->license($application);

        if (! $license instanceof License || $license->documents_status === 'not_applicable' || $license->documents_status === 'complete') {
            return;
        }

        if ($this->readiness->outstandingRequired($application) > 0) {
            return;
        }

        $license->documents_status = 'complete';
        $license->documents_completed_at = now();
        $license->updated_by = $actor->id;
        $license->save();
        $this->logger->log(
            'updated',
            'License documents marked complete',
            'license',
            $license->id,
            $actor,
            ['documents_status' => 'incomplete'],
            ['documents_status' => 'complete'],
        );
    }

    private function license(LicenseApplication $application): ?License
    {
        $license = License::query()
            ->where('application_id', $application->id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('id')
            ->first();

        return $license instanceof License ? $license : null;
    }
}
