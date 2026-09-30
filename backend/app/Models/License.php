<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class License extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'license_no',
        'licensable_type',
        'licensable_id',
        'application_id',
        'license_kind',
        'renewal_count',
        'valid_from',
        'valid_to',
        'issued_at',
        'issued_by',
        'status',
        'certificate_path',
        'verification_token',
        'documents_status',
        'documents_completed_at',
        'issued_with_enforcement',
        'is_legacy',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'issued_at' => 'datetime',
            'documents_completed_at' => 'datetime',
            'issued_with_enforcement' => 'boolean',
            'is_legacy' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'licensable_id');
    }

    /**
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->user_type === 'company') {
            return $query
                ->where('licensable_type', 'company')
                ->where('licensable_id', $user->company_id ?: 0);
        }

        $licensePowers = $user->can('licenses.issue')
            || $user->can('licenses.suspend')
            || $user->can('licenses.cancel')
            || $user->can('licenses.restore');

        if ($user->hasRole('District Officer')) {
            $districtIds = $user->districts()->pluck('districts.id')->all();
            $dealerIds = Dealer::withTrashed()
                ->whereIn('district_id', $districtIds === [] ? [0] : $districtIds)
                ->pluck('id')
                ->all();

            return $query->where(function (Builder $inner) use ($dealerIds) {
                $inner->where('licensable_type', 'company')
                    ->orWhere(function (Builder $dealers) use ($dealerIds) {
                        $dealers->where('licensable_type', 'dealer')
                            ->whereIn('licensable_id', $dealerIds === [] ? [0] : $dealerIds);
                    });
            });
        }

        if ($licensePowers || ($user->can('companies.view') && $user->can('dealers.view'))) {
            return $query;
        }

        if ($user->can('companies.view')) {
            return $query->where('licensable_type', 'company');
        }

        if ($user->can('dealers.view')) {
            return $query->where('licensable_type', 'dealer');
        }

        return $query->whereRaw('0 = 1');
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $field ??= $this->getRouteKeyName();
        $user = Auth::user();

        return $this->newQuery()
            ->where($field, $value)
            ->visibleTo($user instanceof User ? $user : null)
            ->firstOrFail();
    }
}
