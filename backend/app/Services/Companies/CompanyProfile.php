<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\License;
use App\Models\Setting;
use App\Support\SettingValue;
use Illuminate\Support\Carbon;

class CompanyProfile
{
    /**
     * @return array<string, mixed>
     */
    public function present(Company $company): array
    {
        $company->loadMissing('province');
        $license = $this->currentLicense($company);
        $verified = $this->verifiedTechnicalStaff($company);
        $minimum = $this->minimumTechnicalStaff();

        return [
            'company' => $this->company($company, $license),
            'license' => $license ? $this->license($license) : null,
            'alerts' => $this->alerts($verified, $minimum, $license),
            'contacts' => $this->contacts($company),
            'verified_technical_staff' => $verified,
            'minimum_technical_staff' => $minimum,
        ];
    }

    public function verifiedTechnicalStaff(Company $company): int
    {
        return CompanyPerson::query()
            ->where('company_id', $company->id)
            ->where('role', 'technical_staff')
            ->whereNull('end_date')
            ->where('verification_status', 'verified')
            ->whereHas('person', fn ($query) => $query->where('cnic_pending', false))
            ->count();
    }

    public function minimumTechnicalStaff(): int
    {
        $setting = Setting::query()->where('key', 'min_technical_staff_company')->first();

        if (! $setting) {
            return 2;
        }

        $value = SettingValue::typed($setting);

        return is_int($value) && $value > 0 ? $value : 2;
    }

    public function staffNote(Company $company): ?string
    {
        $count = $this->verifiedTechnicalStaff($company);
        $minimum = $this->minimumTechnicalStaff();

        if ($count >= $minimum) {
            return null;
        }

        return "Company will have {$count} verified staff left (minimum {$minimum}).";
    }

    private function currentLicense(Company $company): ?License
    {
        return License::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function company(Company $company, ?License $license): array
    {
        return [
            'id' => $company->id,
            'company_code' => $company->company_code,
            'name' => $company->name,
            'legal_type' => $company->legal_type,
            'ntn' => $company->ntn,
            'incorporation_no' => $company->incorporation_no,
            'incorporation_date' => $company->incorporation_date?->toDateString(),
            'head_office_address' => $company->head_office_address,
            'city' => $company->city,
            'province_id' => $company->province_id,
            'province_name' => $company->province?->name,
            'landline' => $company->landline,
            'mobile' => $company->mobile,
            'email' => $company->email,
            'website' => $company->website,
            'pcpa_member' => $company->pcpa_member,
            'croplife_member' => $company->croplife_member,
            'membership_no' => $company->membership_no,
            'status' => $company->status,
            'expiry_date' => $license?->valid_to?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function license(License $license): array
    {
        $validTo = $license->valid_to?->copy()->startOfDay();
        $days = $validTo ? (int) Carbon::today()->diffInDays($validTo, false) : null;

        return [
            'id' => $license->id,
            'license_no' => $license->license_no,
            'license_kind' => $license->license_kind,
            'status' => $license->status,
            'valid_from' => $license->valid_from?->toDateString(),
            'valid_to' => $license->valid_to?->toDateString(),
            'days_remaining' => $days,
            'documents_status' => $license->documents_status,
        ];
    }

    /**
     * @return list<array{kind: string, message: string}>
     */
    private function alerts(int $verified, int $minimum, ?License $license): array
    {
        $alerts = [];

        if ($verified < $minimum) {
            $alerts[] = [
                'kind' => 'staff',
                'message' => "Only {$verified} verified technical staff (minimum {$minimum}).",
            ];
        }

        if ($license?->documents_status === 'incomplete') {
            $alerts[] = [
                'kind' => 'documents',
                'message' => 'Documents incomplete.',
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function contacts(Company $company): array
    {
        return CompanyPerson::query()
            ->with('person')
            ->where('company_id', $company->id)
            ->where('role', 'contact_person')
            ->whereNull('end_date')
            ->orderBy('start_date')
            ->get()
            ->map(fn (CompanyPerson $row) => [
                'id' => $row->id,
                'name' => $row->person?->full_name,
            ])
            ->all();
    }
}
