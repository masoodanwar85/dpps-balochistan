<?php

namespace App\Services\Statuses;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OfficeNotification;
use App\Services\ActivityLogger;
use App\Services\Applications\ApplicationStages;
use App\Support\SettingValue;
use Illuminate\Database\Eloquent\Model;

class StatusRefresh
{
    public function __construct(
        private ActivityLogger $logger,
        private ApplicationStages $stages,
    ) {}

    public function run(): void
    {
        $this->expireLicenses();
        Company::query()->orderBy('id')->each(fn (Company $company) => $this->party($company, 'company'));
        Dealer::query()->orderBy('id')->each(fn (Dealer $dealer) => $this->party($dealer, 'dealer'));
        $this->renewalWindow();
        LicenseApplication::query()
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn'])
            ->orderBy('id')
            ->each(fn (LicenseApplication $application) => $this->stages->refreshSla($application));
    }

    public function refreshParty(string $type, int $id, ?User $actor = null): void
    {
        $party = match ($type) {
            'company' => Company::query()->find($id),
            'dealer' => Dealer::query()->find($id),
            default => null,
        };

        if (! $party instanceof Model) {
            return;
        }

        $this->party($party, $type, $actor);
    }

    public function currentLicense(string $type, int $id): ?License
    {
        $license = License::query()
            ->where('licensable_type', $type)
            ->where('licensable_id', $id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->orderByDesc('id')
            ->first();

        return $license instanceof License ? $license : null;
    }

    public function daysRemaining(License $license): int
    {
        return (int) now()->startOfDay()->diffInDays($license->valid_to->copy()->startOfDay(), false);
    }

    public function integerSetting(string $key, int $fallback): int
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $fallback;
        }

        $value = SettingValue::typed($setting);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }

    private function expireLicenses(): void
    {
        License::query()
            ->where('status', 'active')
            ->whereDate('valid_to', '<', now()->toDateString())
            ->orderBy('id')
            ->each(function (License $license): void {
                $license->status = 'expired';
                $license->save();
                $this->logger->log(
                    'updated',
                    'License '.$license->license_no.' expired.',
                    'license',
                    $license->id,
                    null,
                    ['status' => 'active'],
                    ['status' => 'expired'],
                    'system',
                );
            });
    }

    private function party(Model $party, string $type, ?User $actor = null): void
    {
        if (in_array($party->status, ['suspended', 'cancelled'], true)) {
            return;
        }

        $license = $this->currentLicense($type, (int) $party->id);
        $next = 'unlicensed';

        if ($license instanceof License) {
            $validTo = $license->valid_to->copy()->startOfDay();
            $today = now()->startOfDay();
            $next = match (true) {
                $validTo->lt($today) => 'expired',
                $validTo->lte($today->copy()->addDays($this->integerSetting('alert_amber_days', 90))) => 'expiring',
                default => 'active',
            };
        }

        if ($party->status === $next) {
            return;
        }

        $from = $party->status;
        $party->status = $next;
        $party->save();
        $this->logger->log(
            'updated',
            ucfirst($type).' status set to '.$next.'.',
            $type,
            (int) $party->id,
            $actor,
            ['status' => $from],
            ['status' => $next],
            $actor instanceof User ? 'web' : 'system',
        );
    }

    private function renewalWindow(): void
    {
        $window = $this->integerSetting('renewal_window_days', 60);
        $companies = Company::query()->whereNotIn('status', ['suspended', 'cancelled'])->orderBy('id')->get();

        foreach ($companies as $company) {
            $license = $this->currentLicense('company', $company->id);

            if (! $license instanceof License) {
                continue;
            }

            $days = $this->daysRemaining($license);

            if ($days < 0 || $days > $window) {
                continue;
            }

            if ($this->renewalFiled($license)) {
                continue;
            }

            $users = User::query()
                ->where('user_type', 'company')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->get();

            foreach ($users as $user) {
                $exists = $user->notifications()
                    ->where('data->kind', 'renewal_window')
                    ->where('data->license_id', $license->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $user->notify(new OfficeNotification(
                    'renewal_window',
                    'Renewal window open',
                    'The renewal window is open for license '.$license->license_no.'.',
                    ['license_id' => $license->id, 'company_id' => $company->id],
                ));
            }
        }
    }

    private function renewalFiled(License $license): bool
    {
        return LicenseApplication::query()
            ->where('previous_license_id', $license->id)
            ->where('application_type', 'renewal')
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->exists();
    }
}
