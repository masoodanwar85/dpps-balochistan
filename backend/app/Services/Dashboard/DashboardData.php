<?php

namespace App\Services\Dashboard;

use App\Models\ApplicationChecklistItem;
use App\Models\Challan;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\District;
use App\Models\Document;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\Person;
use App\Models\User;
use App\Services\Statuses\StatusRefresh;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardData
{
    public function __construct(private StatusRefresh $statuses) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $actor): array
    {
        $red = $this->statuses->integerSetting('alert_red_days', 30);
        $amber = $this->statuses->integerSetting('alert_amber_days', 90);

        return [
            'companies' => $this->partyCounts(Company::query()->visibleTo($actor)),
            'dealers' => $actor->can('dealers.view')
                ? $this->partyCounts(Dealer::query()->visibleTo($actor))
                : $this->emptyParties(),
            'licenses' => [
                'new_this_year' => $this->licenses($actor)->where('license_kind', 'registration')->whereYear('issued_at', now()->year)->count(),
                'renewed_this_year' => $this->licenses($actor)->where('license_kind', 'renewal')->whereYear('issued_at', now()->year)->count(),
                'expired' => $this->licenses($actor)->where('status', 'expired')->count(),
                'suspended' => $this->licenses($actor)->where('status', 'suspended')->count(),
            ],
            'applications' => [
                'new' => $this->openApplications($actor)->where('application_type', 'new')->count(),
                'renewal' => $this->openApplications($actor)->where('application_type', 'renewal')->count(),
                'deficiency_open' => LicenseApplication::query()->visibleTo($actor)->where('status', 'deficiency_issued')->count(),
                'sla_breached' => $this->openApplications($actor)->whereHas(
                    'stageLogs',
                    fn (Builder $query) => $query->whereNull('completed_at')->where('sla_breached', true),
                )->count(),
            ],
            'verification' => [
                'staff' => CompanyPerson::query()
                    ->where('verification_status', 'pending')
                    ->whereNull('end_date')
                    ->whereIn('company_id', Company::query()->visibleTo($actor)->select('id'))
                    ->count(),
                'documents' => $this->pendingDocuments($actor)->count(),
                'products' => CompanyProduct::query()
                    ->where('status', 'pending')
                    ->whereIn('company_id', Company::query()->visibleTo($actor)->select('id'))
                    ->count(),
            ],
            'documents_incomplete' => [
                'companies' => $this->incomplete($actor, 'company')->count(),
                'dealers' => $actor->can('dealers.view') ? $this->incomplete($actor, 'dealer')->count() : 0,
            ],
            'alerts' => $this->alertCounts($actor, null, $red, $amber),
            'alert_red_days' => $red,
            'alert_amber_days' => $amber,
            'year' => (int) now()->year,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expiryAlerts(User $actor, string $party, ?int $districtId): array
    {
        if (! in_array($party, ['company', 'dealer'], true)) {
            $party = 'company';
        }

        if ($party === 'dealer' && ! $actor->can('dealers.view')) {
            return [];
        }

        $red = $this->statuses->integerSetting('alert_red_days', 30);
        $amber = $this->statuses->integerSetting('alert_amber_days', 90);
        $window = $this->statuses->integerSetting('renewal_window_days', 60);
        $rows = [];

        foreach ($this->scopedCurrent($actor, $party, $districtId)->orderBy('valid_to')->limit(100)->get() as $license) {
            $days = $this->statuses->daysRemaining($license);
            $level = $this->level($license, $days, $red, $amber, $window);

            if ($level === null) {
                continue;
            }

            $rows[] = [
                'id' => $license->id,
                'name' => $this->name($license),
                'license_no' => $license->license_no,
                'licensable_type' => $license->licensable_type,
                'licensable_id' => $license->licensable_id,
                'valid_to' => $license->valid_to?->toDateString(),
                'days' => $days,
                'level' => $level,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentsIncomplete(User $actor): array
    {
        $rows = [];

        foreach (['company', 'dealer'] as $type) {
            if ($type === 'dealer' && ! $actor->can('dealers.view')) {
                continue;
            }

            foreach ($this->incomplete($actor, $type)->orderBy('license_no')->limit(100)->get() as $license) {
                $rows[] = [
                    'id' => $license->id,
                    'license_no' => $license->license_no,
                    'applicant_name' => $this->name($license),
                    'licensable_type' => $license->licensable_type,
                    'licensable_id' => $license->licensable_id,
                    'valid_to' => $license->valid_to?->toDateString(),
                    'application_id' => $license->application_id,
                    'documents_status' => $license->documents_status,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    public function search(User $actor, string $type, string $term): array
    {
        $like = '%'.$term.'%';

        return match ($type) {
            'dealer' => $this->searchDealers($actor, $like),
            'license' => $this->searchLicenses($actor, $like),
            'cnic' => $this->searchPeople($actor, preg_replace('/\D/', '', $term) ?: $term, 'cnic'),
            'mobile' => $this->searchMobile($actor, $like),
            'district' => $this->searchDistricts($actor, $like),
            default => $this->searchCompanies($actor, $like),
        };
    }

    /**
     * @param  Builder<Company>|Builder<Dealer>  $query
     * @return array{total: int, active: int, expiring: int, expired: int}
     */
    private function partyCounts(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'expiring' => (clone $query)->where('status', 'expiring')->count(),
            'expired' => (clone $query)->where('status', 'expired')->count(),
        ];
    }

    /**
     * @return array{total: int, active: int, expiring: int, expired: int}
     */
    private function emptyParties(): array
    {
        return ['total' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0];
    }

    /**
     * @return Builder<License>
     */
    private function licenses(User $actor): Builder
    {
        return License::query()->visibleTo($actor);
    }

    /**
     * @return Builder<LicenseApplication>
     */
    private function openApplications(User $actor): Builder
    {
        return LicenseApplication::query()
            ->visibleTo($actor)
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn']);
    }

    /**
     * @return Builder<Document>
     */
    private function pendingDocuments(User $actor): Builder
    {
        $companies = Company::query()->visibleTo($actor)->select('id');
        $dealers = Dealer::query()->visibleTo($actor)->select('id');
        $applications = LicenseApplication::query()->visibleTo($actor)->select('id');
        $items = ApplicationChecklistItem::query()->whereIn('application_id', $applications)->select('id');
        $challans = Challan::query()->whereIn('application_id', $applications)->select('id');

        return Document::query()
            ->where('verification_status', 'pending')
            ->whereNull('replaced_by_id')
            ->where(function (Builder $query) use ($companies, $dealers, $items, $challans) {
                $query->where(function (Builder $inner) use ($companies) {
                    $inner->where('documentable_type', 'company')->whereIn('documentable_id', $companies);
                })->orWhere(function (Builder $inner) use ($dealers) {
                    $inner->where('documentable_type', 'dealer')->whereIn('documentable_id', $dealers);
                })->orWhere(function (Builder $inner) use ($items) {
                    $inner->where('documentable_type', 'application_checklist_item')->whereIn('documentable_id', $items);
                })->orWhere(function (Builder $inner) use ($challans) {
                    $inner->where('documentable_type', 'challan')->whereIn('documentable_id', $challans);
                });
            });
    }

    /**
     * @return Builder<License>
     */
    private function incomplete(User $actor, string $type): Builder
    {
        return $this->scopedCurrent($actor, $type, null)->where('documents_status', 'incomplete');
    }

    /**
     * @return Builder<License>
     */
    private function scopedCurrent(User $actor, string $type, ?int $districtId): Builder
    {
        $query = $this->currentLicenses($type);

        if ($type === 'company') {
            $query->whereIn('licensable_id', Company::query()->visibleTo($actor)->select('id'));
        } else {
            $dealers = Dealer::query()->visibleTo($actor);

            if ($districtId) {
                $dealers->where('district_id', $districtId);
            }

            $query->whereIn('licensable_id', $dealers->select('id'));
        }

        return $query;
    }

    /**
     * @return Builder<License>
     */
    private function currentLicenses(string $type): Builder
    {
        $latest = DB::table('licenses')
            ->select('licensable_id', DB::raw('max(valid_to) as valid_to'))
            ->where('licensable_type', $type)
            ->where('status', '!=', 'superseded')
            ->whereNull('deleted_at')
            ->groupBy('licensable_id');

        return License::query()
            ->where('licensable_type', $type)
            ->where('status', '!=', 'superseded')
            ->whereIn('id', function ($query) use ($type, $latest) {
                $query->from('licenses as pick')
                    ->selectRaw('max(pick.id)')
                    ->joinSub($latest, 'latest', function ($join) {
                        $join->on('pick.licensable_id', '=', 'latest.licensable_id')
                            ->on('pick.valid_to', '=', 'latest.valid_to');
                    })
                    ->where('pick.licensable_type', $type)
                    ->where('pick.status', '!=', 'superseded')
                    ->whereNull('pick.deleted_at')
                    ->groupBy('pick.licensable_id');
            });
    }

    /**
     * @return array{expired: int, under_red: int, under_amber: int, renewal_window: int, document_expiring: int}
     */
    private function alertCounts(User $actor, ?int $districtId, int $red, int $amber): array
    {
        $window = $this->statuses->integerSetting('renewal_window_days', 60);
        $counts = ['expired' => 0, 'under_red' => 0, 'under_amber' => 0, 'renewal_window' => 0, 'document_expiring' => 0];

        foreach (['company', 'dealer'] as $type) {
            if ($type === 'dealer' && ! $actor->can('dealers.view')) {
                continue;
            }

            foreach ($this->scopedCurrent($actor, $type, $type === 'dealer' ? $districtId : null)->get() as $license) {
                $days = $this->statuses->daysRemaining($license);

                if ($days < 0) {
                    $counts['expired']++;
                } elseif ($days <= $red) {
                    $counts['under_red']++;
                } elseif ($days <= $amber) {
                    $counts['under_amber']++;
                }

                if ($days >= 0 && $days <= $window && ! $this->renewalFiled($license)) {
                    $counts['renewal_window']++;
                }

                if ($this->documentExpiring($license)) {
                    $counts['document_expiring']++;
                }
            }
        }

        return $counts;
    }

    private function level(License $license, int $days, int $red, int $amber, int $window): ?string
    {
        if ($days < 0) {
            return 'expired';
        }

        if ($days <= $red) {
            return 'red';
        }

        if ($days <= $amber) {
            return 'amber';
        }

        if ($days <= $window && ! $this->renewalFiled($license)) {
            return 'renewal';
        }

        if ($this->documentExpiring($license)) {
            return 'document';
        }

        return null;
    }

    private function renewalFiled(License $license): bool
    {
        return LicenseApplication::query()
            ->where('previous_license_id', $license->id)
            ->where('application_type', 'renewal')
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->exists();
    }

    private function documentExpiring(License $license): bool
    {
        return Document::query()
            ->where('documentable_type', $license->licensable_type)
            ->where('documentable_id', $license->licensable_id)
            ->where('verification_status', 'verified')
            ->whereNull('replaced_by_id')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', $license->valid_to->toDateString())
            ->exists();
    }

    private function name(License $license): string
    {
        if ($license->licensable_type === 'company') {
            return Company::withTrashed()->find($license->licensable_id)?->name ?? 'Company';
        }

        return Dealer::withTrashed()->find($license->licensable_id)?->shop_name ?? 'Dealer';
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchCompanies(User $actor, string $like): array
    {
        return Company::query()->visibleTo($actor)
            ->where(function (Builder $query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('company_code', 'like', $like);
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Company $company) => [
                'type' => 'company',
                'id' => $company->id,
                'label' => $company->name.' ('.$company->company_code.')',
                'route' => '/companies/'.$company->id,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchDealers(User $actor, string $like): array
    {
        if (! $actor->can('dealers.view')) {
            return [];
        }

        return Dealer::query()->visibleTo($actor)
            ->where(function (Builder $query) use ($like) {
                $query->where('shop_name', 'like', $like)->orWhere('dealer_code', 'like', $like);
            })
            ->orderBy('shop_name')
            ->limit(20)
            ->get()
            ->map(fn (Dealer $dealer) => [
                'type' => 'dealer',
                'id' => $dealer->id,
                'label' => $dealer->shop_name.' ('.$dealer->dealer_code.')',
                'route' => '/dealers/'.$dealer->id,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchLicenses(User $actor, string $like): array
    {
        return License::query()->visibleTo($actor)
            ->where('license_no', 'like', $like)
            ->orderBy('license_no')
            ->limit(20)
            ->get()
            ->map(fn (License $license) => [
                'type' => 'license',
                'id' => $license->id,
                'label' => $license->license_no,
                'route' => $license->application_id
                    ? '/applications/'.$license->application_id
                    : ($license->licensable_type === 'dealer' ? '/dealers/' : '/companies/').$license->licensable_id,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchPeople(User $actor, string $term, string $column): array
    {
        if (! $actor->can('persons.view') || $term === '') {
            return [];
        }

        return Person::query()
            ->where($column, 'like', '%'.$term.'%')
            ->orderBy('full_name')
            ->limit(20)
            ->get()
            ->map(fn ($person) => [
                'type' => 'person',
                'id' => $person->id,
                'label' => $person->full_name.' ('.$person->cnic.')',
                'route' => '/persons?cnic='.$person->cnic,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchMobile(User $actor, string $like): array
    {
        $companies = Company::query()->visibleTo($actor)
            ->where('mobile', 'like', $like)
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Company $company) => [
                'type' => 'company',
                'id' => $company->id,
                'label' => $company->name.' ('.$company->mobile.')',
                'route' => '/companies/'.$company->id,
            ])
            ->all();
        $people = $actor->can('persons.view') ? $this->searchPeople($actor, trim($like, '%'), 'mobile') : [];
        $dealers = [];

        if ($actor->can('dealers.view')) {
            $dealers = Dealer::query()->visibleTo($actor)
                ->where('mobile', 'like', $like)
                ->orderBy('shop_name')
                ->limit(20)
                ->get()
                ->map(fn (Dealer $dealer) => [
                    'type' => 'dealer',
                    'id' => $dealer->id,
                    'label' => $dealer->shop_name.' ('.$dealer->mobile.')',
                    'route' => '/dealers/'.$dealer->id,
                ])
                ->all();
        }

        return array_slice(array_merge($companies, $people, $dealers), 0, 20);
    }

    /**
     * @return list<array{type: string, id: int, label: string, route: string}>
     */
    private function searchDistricts(User $actor, string $like): array
    {
        $query = District::query()->where('name', 'like', $like)->orderBy('name');

        if ($actor->hasRole('District Officer')) {
            $ids = $actor->districts()->pluck('districts.id')->all();
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        return $query->limit(20)->get()->map(fn ($district) => [
            'type' => 'district',
            'id' => $district->id,
            'label' => $district->name,
            'route' => '/dealers?district='.$district->id,
        ])->all();
    }
}
