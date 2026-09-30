<?php

namespace App\Services\Applications;

use App\Models\Dealer;
use App\Models\District;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ApplicationDirectory
{
    /**
     * @return LengthAwarePaginator<int, LicenseApplication>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        $query = LicenseApplication::query()->visibleTo($actor);

        if ($request->filled('filter.licensable_type')) {
            $query->where('licensable_type', $request->string('filter.licensable_type')->value());
        }

        if ($request->filled('filter.application_type')) {
            $query->where('application_type', $request->string('filter.application_type')->value());
        }

        if ($request->filled('filter.stage')) {
            $code = $request->string('filter.stage')->value();
            $query->whereHas('currentStage', fn ($stage) => $stage->where('code', $code));
        }

        $status = $request->string('filter.status')->value() ?: 'open';

        if ($status === 'open') {
            $query->whereNotIn('status', ['issued', 'rejected', 'withdrawn']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->boolean('filter.sla_breached')) {
            $query->whereHas('stageLogs', fn ($log) => $log->whereNull('completed_at')->where('sla_breached', true));
        }

        if ($request->filled('filter.district_id')) {
            $dealerIds = Dealer::withTrashed()
                ->where('district_id', $request->integer('filter.district_id'))
                ->pluck('id');
            $query->where('licensable_type', 'dealer')->whereIn('licensable_id', $dealerIds->all() === [] ? [0] : $dealerIds->all());
        }

        if (! $request->filled('per_page')) {
            $request->merge(['per_page' => 25]);
        }

        return ListQuery::paginate($request, $query, ['application_no', 'submitted_at', 'status'], '-submitted_at');
    }

    /**
     * @return array{stages: list<array{code: string, name: string, sequence: int}>, districts: list<array{id: int, name: string}>}
     */
    public function meta(User $actor): array
    {
        $stages = WorkflowStage::query()
            ->where('entity_type', 'company')
            ->orderBy('sequence')
            ->get()
            ->map(fn (WorkflowStage $stage) => [
                'code' => $stage->code,
                'name' => $stage->sequence.'. '.$stage->name,
                'sequence' => $stage->sequence,
            ])
            ->all();

        $districts = District::query()->where('is_active', true)->orderBy('name');

        if ($actor->hasRole('District Officer')) {
            $ids = $actor->districts()->pluck('districts.id');
            $districts->whereIn('id', $ids->all() === [] ? [0] : $ids->all());
        }

        return [
            'stages' => $stages,
            'districts' => $districts->get(['id', 'name'])->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
            ])->all(),
        ];
    }
}
