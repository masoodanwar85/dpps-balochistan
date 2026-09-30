<?php

namespace App\Services\Notifications;

use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Notifications\OfficeNotification;

class InAppNotifications
{
    public function licenseIssued(License $license): void
    {
        if ($license->licensable_type !== 'company') {
            return;
        }

        $this->companyUsers((int) $license->licensable_id, new OfficeNotification(
            'license_issued',
            'License issued',
            'License '.$license->license_no.' has been issued.',
            ['license_id' => $license->id, 'application_id' => $license->application_id],
        ));
    }

    public function deficiencyIssued(LicenseApplication $application, string $letterNo): void
    {
        if ($application->licensable_type !== 'company') {
            return;
        }

        $this->companyUsers((int) $application->licensable_id, new OfficeNotification(
            'deficiency_letter',
            'Deficiency letter issued',
            'Deficiency letter '.$letterNo.' was issued.',
            ['application_id' => $application->id],
        ));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function portalSubmitted(string $title, string $body, array $meta = []): void
    {
        User::role(['Super Admin', 'Director', 'Registration Officer'])
            ->where('user_type', 'staff')
            ->where('is_active', true)
            ->orderBy('id')
            ->each(fn (User $user) => $user->notify(new OfficeNotification(
                'portal_submission',
                $title,
                $body,
                $meta,
            )));
    }

    public function submissionDecided(int $companyId, string $title, string $body): void
    {
        $this->companyUsers($companyId, new OfficeNotification(
            'submission_decided',
            $title,
            $body,
            ['company_id' => $companyId],
        ));
    }

    private function companyUsers(int $companyId, OfficeNotification $notification): void
    {
        User::query()
            ->where('user_type', 'company')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->each(fn (User $user) => $user->notify($notification));
    }
}
