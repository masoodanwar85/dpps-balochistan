<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallanItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'challan_id',
        'item_type',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(Challan::class);
    }
}
