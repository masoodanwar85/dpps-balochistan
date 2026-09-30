<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationPenalty extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'penalty_type',
        'reference_rate',
        'helper_info',
        'basis',
        'standard_amount',
        'final_amount',
        'waiver_reason',
        'waiver_order_no',
        'waiver_approved_by',
        'entered_by',
        'entered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_rate' => 'decimal:2',
            'standard_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'entered_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenseApplication::class, 'application_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiver_approved_by');
    }
}
