<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeficiencyLetterItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'deficiency_letter_id',
        'application_checklist_item_id',
        'remarks',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(DeficiencyLetter::class, 'deficiency_letter_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ApplicationChecklistItem::class, 'application_checklist_item_id');
    }
}
