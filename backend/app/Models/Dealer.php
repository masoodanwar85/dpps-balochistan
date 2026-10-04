<?php

namespace App\Models;

use Database\Factories\DealerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Dealer extends Model
{
    /** @use HasFactory<DealerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dealer_code',
        'shop_name',
        'normalized_name',
        'district_id',
        'tehsil_id',
        'business_address',
        'gps_lat',
        'gps_lng',
        'mobile',
        'email',
        'status',
        'legacy_reg_no',
        'legacy_notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(DealerOwner::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_dealers')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Dealer>  $query
     * @return Builder<Dealer>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || ! $user->can('dealers.view')) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasRole('District Officer')) {
            $ids = $user->districts()->pluck('districts.id');
            $query->whereIn('dealers.district_id', $ids->all() === [] ? [0] : $ids->all());
        }

        return $query;
    }

    /**
     * @param  Builder<Dealer>  $query
     * @return Builder<Dealer>
     */
    public function scopeWithExpiry(Builder $query): Builder
    {
        return $query->addSelect(['expiry_date' => License::query()
            ->select('valid_to')
            ->whereColumn('licenses.licensable_id', 'dealers.id')
            ->where('licenses.licensable_type', 'dealer')
            ->where('licenses.status', '!=', 'superseded')
            ->orderByDesc('licenses.valid_to')
            ->limit(1),
        ]);
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
