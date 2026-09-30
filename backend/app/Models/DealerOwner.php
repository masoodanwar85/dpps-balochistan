<?php

namespace App\Models;

use Database\Factories\DealerOwnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class DealerOwner extends Model
{
    /** @use HasFactory<DealerOwnerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dealer_id',
        'person_id',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $row = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();
        $user = Auth::user();
        $visible = $row instanceof self
            && Dealer::query()->visibleTo($user instanceof User ? $user : null)->whereKey($row->dealer_id)->exists();

        if (! $visible || ! $row instanceof self) {
            abort(404);
        }

        return $row;
    }
}
