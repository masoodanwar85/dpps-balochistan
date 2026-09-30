<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DeficiencyLetter extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'letter_no',
        'issued_at',
        'reply_due_date',
        'response_received_at',
        'status',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'reply_due_date' => 'date',
            'response_received_at' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenseApplication::class, 'application_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeficiencyLetterItem::class);
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $row = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();
        $user = Auth::user();
        $actor = $user instanceof User ? $user : null;
        $visible = $row instanceof self
            && LicenseApplication::query()->visibleTo($actor)->whereKey($row->application_id)->exists();

        if (! $visible) {
            abort(404);
        }

        return $row;
    }
}
