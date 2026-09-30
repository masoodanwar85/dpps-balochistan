<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseStatusHistory extends Model
{
    protected $table = 'license_status_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'license_id',
        'from_status',
        'to_status',
        'reason',
        'order_no',
        'effective_date',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
