<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ApplicationChecklistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'checklist_item_id',
        'title_snapshot',
        'annex_code_snapshot',
        'status',
        'page_count',
        'officer_remarks',
        'verified_by',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'page_count' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenseApplication::class, 'application_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
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
