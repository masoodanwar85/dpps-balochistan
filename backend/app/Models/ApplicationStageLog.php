<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStageLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'stage_id',
        'entered_at',
        'due_at',
        'completed_at',
        'acted_by',
        'outcome',
        'remarks',
        'sla_breached',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'sla_breached' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenseApplication::class, 'application_id');
    }
}
