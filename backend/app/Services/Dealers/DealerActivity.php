<?php

namespace App\Services\Dealers;

use App\Models\ActivityLog;
use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;

class DealerActivity
{
    /**
     * @return Builder<ActivityLog>
     */
    public function query(Dealer $dealer): Builder
    {
        $owners = DealerOwner::withTrashed()->where('dealer_id', $dealer->id)->pluck('id');
        $documents = Document::withTrashed()
            ->where('documentable_type', 'dealer')
            ->where('documentable_id', $dealer->id)
            ->pluck('id');

        return ActivityLog::query()
            ->with('user:id,name')
            ->where(function (Builder $query) use ($dealer, $owners, $documents) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('subject_type', 'dealer')
                    ->where('subject_id', $dealer->id));
                $this->orSubjects($query, 'dealer_owner', $owners->all());
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
