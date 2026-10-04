<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_code',
        'name',
        'normalized_name',
        'legal_type',
        'ntn',
        'incorporation_no',
        'incorporation_date',
        'head_office_address',
        'city',
        'province_id',
        'landline',
        'mobile',
        'email',
        'website',
        'pcpa_member',
        'croplife_member',
        'membership_no',
        'csr',
        'rnd',
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
            'incorporation_date' => 'date',
            'pcpa_member' => 'boolean',
            'croplife_member' => 'boolean',
            'csr' => 'boolean',
            'rnd' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function dealers(): BelongsToMany
    {
        return $this->belongsToMany(Dealer::class, 'company_dealers')
            ->withTimestamps();
    }

    public function csrRndFiles(): HasMany
    {
        return $this->hasMany(CompanyCsrRndFile::class);
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->user_type === 'company') {
            $query->whereKey($user->company_id ?? 0);
        }

        return $query;
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeWithExpiry(Builder $query): Builder
    {
        return $query->addSelect(['expiry_date' => License::query()
            ->select('valid_to')
            ->whereColumn('licenses.licensable_id', 'companies.id')
            ->where('licenses.licensable_type', 'company')
            ->where('licenses.status', '!=', 'superseded')
            ->orderByDesc('licenses.valid_to')
            ->limit(1),
        ]);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();
        $user = Auth::user();

        return $this->newQuery()
            ->where($field, $value)
            ->visibleTo($user instanceof User ? $user : null)
            ->firstOrFail();
    }
}
