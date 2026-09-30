<?php

namespace App\Services\Licenses;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LicenseDirectory
{
    /**
     * @return Builder<License>
     */
    public function query(Request $request, User $actor): Builder
    {
        $query = License::query()->visibleTo($actor);

        if ($request->filled('filter.licensable_type')) {
            $query->where('licensable_type', $request->string('filter.licensable_type')->value());
        }

        if ($request->filled('filter.licensable_id')) {
            $query->where('licensable_id', $request->integer('filter.licensable_id'));
        }

        if ($request->filled('filter.license_kind')) {
            $query->where('license_kind', $request->string('filter.license_kind')->value());
        }

        if ($request->filled('filter.status')) {
            $query->where('status', $request->string('filter.status')->value());
        }

        if ($request->filled('filter.district_id')) {
            $dealerIds = Dealer::withTrashed()
                ->where('district_id', $request->integer('filter.district_id'))
                ->pluck('id')
                ->all();
            $query->where('licensable_type', 'dealer')
                ->whereIn('licensable_id', $dealerIds === [] ? [0] : $dealerIds);
        }

        if ($request->filled('filter.valid_from')) {
            $query->whereDate('valid_to', '>=', $request->string('filter.valid_from')->value());
        }

        if ($request->filled('filter.valid_to')) {
            $query->whereDate('valid_from', '<=', $request->string('filter.valid_to')->value());
        }

        if ($request->filled('filter.issued_year')) {
            $query->whereYear('issued_at', $request->integer('filter.issued_year'));
        }

        if ($request->filled('filter.documents_status')) {
            $query->where('documents_status', $request->string('filter.documents_status')->value());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->value().'%';
            $query->where('license_no', 'like', $search);
        }

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, License>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        return ListQuery::paginate(
            $request,
            $this->query($request, $actor),
            ['license_no', 'valid_from', 'valid_to', 'status', 'issued_at'],
            '-issued_at',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function present(License $license): array
    {
        $application = $license->application_id
            ? LicenseApplication::withTrashed()->find($license->application_id)
            : null;

        return [
            'id' => $license->id,
            'license_no' => $license->license_no,
            'applicant_name' => $application instanceof LicenseApplication
                ? $application->applicantName()
                : $this->name($license),
            'licensable_type' => $license->licensable_type,
            'licensable_id' => $license->licensable_id,
            'license_kind' => $license->license_kind,
            'status' => $license->status,
            'district_name' => $license->licensable_type === 'dealer'
                ? Dealer::withTrashed()->find($license->licensable_id)?->district?->name
                : null,
            'valid_from' => $license->valid_from?->toDateString(),
            'valid_to' => $license->valid_to?->toDateString(),
            'documents_status' => $license->documents_status,
            'application_id' => $license->application_id,
        ];
    }

    private function name(License $license): string
    {
        if ($license->licensable_type === 'company') {
            return Company::withTrashed()->find($license->licensable_id)?->name ?? 'Company';
        }

        return Dealer::withTrashed()->find($license->licensable_id)?->shop_name ?? 'Dealer';
    }
}
