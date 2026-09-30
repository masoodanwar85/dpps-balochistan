<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Challan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'challan_no',
        'bank_name',
        'branch',
        'payment_date',
        'amount',
        'document_id',
        'verification_status',
        'verified_by',
        'verified_at',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenseApplication::class, 'application_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChallanItem::class);
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $row = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();
        $user = Auth::user();
        $visible = $row instanceof self
            && LicenseApplication::query()->visibleTo($user instanceof User ? $user : null)->whereKey($row->application_id)->exists();

        if (! $visible) {
            abort(404);
        }

        return $row;
    }
}
