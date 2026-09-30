<?php

namespace App\Services\Companies;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyAsset;
use App\Models\CompanyPerson;
use App\Models\CompanyPremise;
use App\Models\CompanyProduct;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;

class CompanyActivity
{
    /**
     * @return Builder<ActivityLog>
     */
    public function query(Company $company): Builder
    {
        $people = CompanyPerson::withTrashed()->where('company_id', $company->id)->pluck('id');
        $premises = CompanyPremise::query()->where('company_id', $company->id)->pluck('id');
        $assets = CompanyAsset::query()->where('company_id', $company->id)->pluck('id');
        $products = CompanyProduct::withTrashed()->where('company_id', $company->id)->pluck('id');
        $documents = Document::withTrashed()
            ->where('documentable_type', 'company')
            ->where('documentable_id', $company->id)
            ->pluck('id');

        return ActivityLog::query()
            ->with('user:id,name')
            ->where(function (Builder $query) use ($company, $people, $premises, $assets, $products, $documents) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('subject_type', 'company')
                    ->where('subject_id', $company->id));
                $this->orSubjects($query, 'company_person', $people->all());
                $this->orSubjects($query, 'company_premise', $premises->all());
                $this->orSubjects($query, 'company_asset', $assets->all());
                $this->orSubjects($query, 'company_product', $products->all());
                $this->orSubjects($query, 'document', $documents->all());
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'user_name' => $log->user?->name,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @param  list<int>  $ids
     */
    private function orSubjects(Builder $query, string $type, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $query->orWhere(fn (Builder $inner) => $inner
            ->where('subject_type', $type)
            ->whereIn('subject_id', $ids));
    }
}
