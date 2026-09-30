<?php

namespace App\Services\Applications;

use App\Models\CompanyPerson;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Services\Companies\CompanyProfile;
use Illuminate\Support\Facades\DB;

class ApplicationFees
{
    public function __construct(private CompanyProfile $profile) {}

    /**
     * @return array{fee_type: string, label: string, amount: string, effective_from: string}|null
     */
    public function quote(LicenseApplication $application): ?array
    {
        $date = ($application->submitted_at ?? now())->toDateString();
        $feeType = $application->application_type === 'renewal' ? 'renewal' : 'registration';
        $row = DB::table('fee_structures')
            ->where('entity_type', $application->licensable_type)
            ->where('fee_type', $feeType)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fee_type' => $feeType,
            'label' => $feeType === 'renewal' ? 'Renewal fee' : 'Registration fee',
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'effective_from' => (string) $row->effective_from,
        ];
    }

    /**
     * @return array{rates: array<string, array{amount: string, unit: string}|null>, helpers: list<array{penalty_type: string, text: string}>}
     */
    public function guidance(LicenseApplication $application): array
    {
        $helpers = [];

        if ((int) $application->late_days > 0) {
            $days = (int) $application->late_days;
            $word = $days === 1 ? 'day' : 'days';
            $helpers[] = [
                'penalty_type' => 'late_renewal',
                'text' => "Submitted {$days} {$word} after expiry.",
            ];
        }

        $months = $this->shortStaffMonths($application);

        if ($months !== null && $months > 0) {
            $word = $months === 1 ? 'month' : 'months';
            $helpers[] = [
                'penalty_type' => 'no_technical_staff',
                'text' => "Records show {$months} {$word} below minimum technical staff in the last license period.",
            ];
        }

        return [
            'rates' => [
                'late_renewal' => $this->rate($application, 'late_renewal_per_day', 'day'),
                'no_technical_staff' => $application->licensable_type === 'company'
                    ? $this->rate($application, 'no_technical_staff_per_month', 'month')
                    : null,
                'restoration' => $this->rate($application, 'restoration_per_month', 'month'),
            ],
            'helpers' => $helpers,
        ];
    }

    public function refreshTotals(LicenseApplication $application): void
    {
        $penaltyTotal = (float) $application->penalties()->sum('final_amount');
        $application->penalty_total = $penaltyTotal;
        $application->total_payable = (float) $application->fee_amount + $penaltyTotal;
        $application->save();
    }

    /**
     * @return array{amount: string, unit: string}|null
     */
    private function rate(LicenseApplication $application, string $feeType, string $unit): ?array
    {
        $date = ($application->submitted_at ?? now())->toDateString();
        $row = DB::table('fee_structures')
            ->where('entity_type', $application->licensable_type)
            ->where('fee_type', $feeType)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'unit' => $unit,
        ];
    }

    private function shortStaffMonths(LicenseApplication $application): ?int
    {
        if ($application->licensable_type !== 'company') {
            return null;
        }

        $license = $this->periodLicense($application);

        if (! $license instanceof License) {
            return null;
        }

        $minimum = $this->profile->minimumTechnicalStaff();
        $people = CompanyPerson::query()
            ->where('company_id', $application->licensable_id)
            ->where('role', 'technical_staff')
            ->where('verification_status', 'verified')
            ->whereHas('person', fn ($query) => $query->where('cnic_pending', false))
            ->get(['start_date', 'end_date']);
        $from = $license->valid_from->copy()->startOfMonth();
        $until = $license->valid_to->copy()->startOfMonth();
        $short = 0;

        for ($cursor = $from->copy(); $cursor->lte($until); $cursor->addMonth()) {
            $monthStart = $cursor->copy()->startOfMonth()->max($license->valid_from->copy());
            $monthEnd = $cursor->copy()->endOfMonth()->min($license->valid_to->copy());
            $count = $people->filter(function (CompanyPerson $person) use ($monthStart, $monthEnd) {
                $start = $person->start_date->copy();
                $end = $person->end_date?->copy();

                return $start->lte($monthEnd) && ($end === null || $end->gte($monthStart));
            })->count();

            if ($count < $minimum) {
                $short++;
            }
        }

        return $short;
    }

    private function periodLicense(LicenseApplication $application): ?License
    {
        if ($application->previous_license_id) {
            $license = License::query()->find($application->previous_license_id);

            return $license instanceof License ? $license : null;
        }

        $license = License::query()
            ->where('licensable_type', $application->licensable_type)
            ->where('licensable_id', $application->licensable_id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->first();

        return $license instanceof License ? $license : null;
    }
}
