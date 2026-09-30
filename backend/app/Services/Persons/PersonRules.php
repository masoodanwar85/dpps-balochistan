<?php

namespace App\Services\Persons;

use App\Models\CompanyPerson;
use App\Models\DealerOwner;
use App\Models\Person;
use Illuminate\Support\Carbon;

class PersonRules
{
    public function digits(string $cnic): string
    {
        return preg_replace('/\D/', '', $cnic) ?? '';
    }

    public function displayCnic(?string $cnic): ?string
    {
        if ($cnic === null || strlen($cnic) !== 13) {
            return $cnic;
        }

        return substr($cnic, 0, 5).'-'.substr($cnic, 5, 7).'-'.substr($cnic, 12, 1);
    }

    public function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    /**
     * R-08. End employment (step 13) calls this before saving dates.
     *
     * @return list<string>
     */
    public function employmentDateErrors(string $startDate, ?string $endDate): array
    {
        $errors = [];
        $start = Carbon::parse($startDate)->startOfDay();

        if ($start->gt(Carbon::today()->addDays(30))) {
            $errors[] = 'The start date cannot be more than 30 days in the future.';
        }

        if ($endDate !== null && $endDate !== '') {
            $end = Carbon::parse($endDate)->startOfDay();

            if ($end->lt($start)) {
                $errors[] = 'The end date cannot be before the start date.';
            }
        }

        return $errors;
    }

    public function countsAsVerifiedStaff(Person $person, CompanyPerson $assignment): bool
    {
        return ! $person->cnic_pending
            && $assignment->role === 'technical_staff'
            && $assignment->end_date === null
            && $assignment->verification_status === 'verified'
            && $assignment->deleted_at === null;
    }

    public function blocksLicense(Person $person): bool
    {
        if (! $person->cnic_pending) {
            return false;
        }

        $companyLink = CompanyPerson::query()
            ->where('person_id', $person->id)
            ->whereNull('end_date')
            ->whereIn('role', ['ceo', 'director', 'technical_staff'])
            ->exists();

        if ($companyLink) {
            return true;
        }

        return DealerOwner::query()
            ->where('person_id', $person->id)
            ->whereNull('end_date')
            ->exists();
    }

    /**
     * @return list<array{rule: string, for: string, message: string}>
     */
    public function conflicts(Person $person): array
    {
        $blocks = [];

        $activeTechnicalStaff = CompanyPerson::query()
            ->with('company')
            ->where('person_id', $person->id)
            ->where('role', 'technical_staff')
            ->whereNull('end_date')
            ->where('verification_status', '!=', 'rejected')
            ->orderBy('start_date')
            ->get();

        foreach ($activeTechnicalStaff as $assignment) {
            $company = $assignment->company?->name ?? 'another company';
            $start = $assignment->start_date?->toDateString();
            $blocks[] = [
                'rule' => 'R-01',
                'for' => 'technical_staff',
                'message' => "This person is active technical staff at {$company} since {$start}.",
            ];
            $blocks[] = [
                'rule' => 'R-02',
                'for' => 'technical_staff',
                'message' => "Enter the end date at {$company} before moving this person to another company.",
            ];
            $blocks[] = [
                'rule' => 'R-03',
                'for' => 'dealer_owner',
                'message' => "This person is active technical staff at {$company} and cannot be added as an active dealer owner.",
            ];
        }

        $activeOwners = DealerOwner::query()
            ->with('dealer')
            ->where('person_id', $person->id)
            ->whereNull('end_date')
            ->orderBy('start_date')
            ->get();

        foreach ($activeOwners as $owner) {
            $shop = $owner->dealer?->shop_name ?? 'a dealer shop';
            $blocks[] = [
                'rule' => 'R-03',
                'for' => 'technical_staff',
                'message' => "This person is an active owner of {$shop} and cannot be added as technical staff.",
            ];
        }

        return $blocks;
    }

    /**
     * @return list<array{kind: string, label: string, organisation: ?string, start_date: ?string, end_date: ?string, current: bool}>
     */
    public function roles(Person $person): array
    {
        $roles = [];

        $assignments = CompanyPerson::query()
            ->with('company')
            ->where('person_id', $person->id)
            ->get();

        foreach ($assignments as $assignment) {
            $roles[] = [
                'kind' => $assignment->role,
                'label' => $this->roleLabel($assignment->role),
                'organisation' => $assignment->company?->name,
                'start_date' => $assignment->start_date?->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
                'current' => $assignment->end_date === null,
            ];
        }

        $owners = DealerOwner::query()
            ->with('dealer')
            ->where('person_id', $person->id)
            ->get();

        foreach ($owners as $owner) {
            $roles[] = [
                'kind' => 'dealer_owner',
                'label' => 'Dealer owner',
                'organisation' => $owner->dealer?->shop_name,
                'start_date' => $owner->start_date?->toDateString(),
                'end_date' => $owner->end_date?->toDateString(),
                'current' => $owner->end_date === null,
            ];
        }

        usort($roles, function (array $left, array $right): int {
            if ($left['current'] !== $right['current']) {
                return $left['current'] ? -1 : 1;
            }

            return strcmp((string) $right['start_date'], (string) $left['start_date']);
        });

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function mobileWarnings(Person $person, string $mobile, ?string $altMobile): array
    {
        $numbers = [];

        foreach ([$mobile, $altMobile] as $number) {
            if (is_string($number) && trim($number) !== '') {
                $numbers[] = trim($number);
            }
        }

        $warnings = [];

        foreach (array_unique($numbers) as $number) {
            $other = Person::query()
                ->where('id', '!=', $person->id)
                ->where(function ($query) use ($number) {
                    $query->where('mobile', $number)->orWhere('alt_mobile', $number);
                })
                ->first();

            if ($other) {
                $warnings[] = "This mobile number already belongs to {$other->full_name}.";
            }
        }

        return $warnings;
    }

    public function roleLabel(string $role): string
    {
        return match ($role) {
            'ceo' => 'CEO',
            'director' => 'Director',
            'technical_staff' => 'Technical Staff',
            'contact_person' => 'Contact person',
            'authorized_rep' => 'Authorized rep',
            'dealer_owner' => 'Dealer owner',
            default => $role,
        };
    }
}
