<?php

namespace App\Services\Licenses;

use App\Models\ApplicationChecklistItem;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\DealerOwner;
use App\Models\Document;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Services\Companies\CompanyProfile;
use App\Support\SettingValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LicenseReadiness
{
    public function __construct(
        private CompanyProfile $profile,
        private LicenseNumbers $numbers,
    ) {}

    public function refresh(LicenseApplication $application): void
    {
        $application->loadMissing('currentStage');

        if ($application->currentStage?->code !== 'issuance' || ! $application->isOpen()) {
            return;
        }

        if (! in_array($application->status, ['fee_pending', 'ready_to_issue'], true)) {
            return;
        }

        $next = $this->blockers($application) === [] ? 'ready_to_issue' : 'fee_pending';

        if ($application->status !== $next) {
            $application->status = $next;
            $application->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function assessment(LicenseApplication $application): array
    {
        $dates = $this->dates($application);
        $enforced = $this->enforced();
        $outstanding = $this->outstandingRequired($application);

        return [
            'blockers' => $this->blockers($application),
            'warnings' => $enforced ? [] : $this->periodWarnings($application, $dates['valid_to']),
            'license_no' => $this->numbers->preview($application),
            'valid_from' => $dates['valid_from']->toDateString(),
            'valid_to' => $dates['valid_to']->toDateString(),
            'product_count' => $this->productCount($application),
            'documents_status' => $outstanding === 0 ? 'complete' : 'incomplete',
            'enforcement' => $enforced,
            'outstanding_required' => $outstanding,
            'required_total' => $this->requiredItems($application)->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function blockers(LicenseApplication $application): array
    {
        $blockers = [];
        $verified = (float) $application->challans()->where('verification_status', 'verified')->sum('amount');
        $undecided = $application->challans()->where('verification_status', '!=', 'verified')->exists();

        if ($application->challans()->doesntExist() || $undecided) {
            $blockers[] = 'Challan not verified.';
        }

        if ($verified < (float) $application->total_payable) {
            $blockers[] = 'The verified challans do not cover the total payable.';
        }

        if ($application->penalties()
            ->whereNotNull('standard_amount')
            ->whereColumn('final_amount', '<', 'standard_amount')
            ->whereNull('waiver_approved_by')
            ->exists()) {
            $blockers[] = 'A reduced penalty is waiting for waiver approval.';
        }

        if ($application->licensable_type === 'company' && $this->profile->verifiedTechnicalStaff($this->company($application)) < $this->profile->minimumTechnicalStaff()) {
            $blockers[] = 'The company does not have the minimum verified technical staff.';
        }

        if ($this->pendingCnic($application)) {
            $blockers[] = 'A linked person still has a pending CNIC.';
        }

        if ($this->enforced()) {
            if ($this->outstandingRequired($application) > 0) {
                $blockers[] = 'Required checklist items are still missing or not verified.';
            }

            if ($this->periodWarnings($application, $this->dates($application)['valid_to']) !== []) {
                $blockers[] = 'A document must cover the license period.';
            }
        }

        return $blockers;
    }

    /**
     * @return array{valid_from: Carbon, valid_to: Carbon}
     */
    public function dates(LicenseApplication $application): array
    {
        $previous = $application->previous_license_id
            ? License::query()->find($application->previous_license_id)
            : null;
        $onTime = $application->application_type === 'renewal'
            && $previous instanceof License
            && (int) $application->late_days === 0;
        $from = $onTime
            ? $previous->valid_to->copy()->startOfDay()->addDay()
            : now()->startOfDay();
        $months = $application->licensable_type === 'dealer'
            ? $this->months('dealer_license_period_months', 12)
            : $this->months('company_license_period_months', 12);

        return [
            'valid_from' => $from,
            'valid_to' => $from->copy()->addMonths($months)->subDay(),
        ];
    }

    public function enforced(): bool
    {
        $setting = Setting::query()->where('key', 'enforce_document_requirements')->first();

        if (! $setting) {
            return false;
        }

        return SettingValue::typed($setting) === true;
    }

    public function outstandingRequired(LicenseApplication $application): int
    {
        return $this->requiredItems($application)
            ->filter(fn (ApplicationChecklistItem $item) => ! in_array($item->status, ['verified', 'not_applicable'], true))
            ->count();
    }

    /**
     * @return list<string>
     */
    private function periodWarnings(LicenseApplication $application, Carbon $validTo): array
    {
        $warnings = [];

        foreach ($application->checklistItems()->with('source')->get() as $item) {
            if (! $item->source?->must_cover_license_period || $item->status === 'not_applicable') {
                continue;
            }

            $document = Document::query()
                ->where('documentable_type', 'application_checklist_item')
                ->where('documentable_id', $item->id)
                ->whereNull('replaced_by_id')
                ->orderByDesc('id')
                ->first();
            $expiry = $document?->expiry_date;

            if (! $expiry instanceof Carbon || $expiry->copy()->startOfDay()->lt($validTo->copy()->startOfDay())) {
                $warnings[] = 'A document expires before the license ends.';
                break;
            }
        }

        return $warnings;
    }

    /**
     * @return Collection<int, ApplicationChecklistItem>
     */
    private function requiredItems(LicenseApplication $application): Collection
    {
        return $application->checklistItems()->with('source')->get()
            ->filter(fn (ApplicationChecklistItem $item) => (bool) $item->source?->is_required)
            ->values();
    }

    private function productCount(LicenseApplication $application): int
    {
        if ($application->licensable_type !== 'company') {
            return 0;
        }

        return CompanyProduct::query()
            ->where('company_id', $application->licensable_id)
            ->where('status', 'approved')
            ->whereNull('approved_in_license_id')
            ->count();
    }

    private function pendingCnic(LicenseApplication $application): bool
    {
        if ($application->licensable_type === 'company') {
            return CompanyPerson::query()
                ->where('company_id', $application->licensable_id)
                ->whereNull('end_date')
                ->whereIn('role', ['technical_staff', 'ceo', 'director'])
                ->whereHas('person', fn ($query) => $query->where('cnic_pending', true))
                ->exists();
        }

        return DealerOwner::query()
            ->where('dealer_id', $application->licensable_id)
            ->whereNull('end_date')
            ->whereHas('person', fn ($query) => $query->where('cnic_pending', true))
            ->exists();
    }

    private function company(LicenseApplication $application): Company
    {
        $company = Company::query()->find($application->licensable_id);

        return $company instanceof Company ? $company : new Company;
    }

    private function months(string $key, int $fallback): int
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $fallback;
        }

        $value = SettingValue::typed($setting);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
