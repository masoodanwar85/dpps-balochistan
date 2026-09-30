<?php

namespace App\Models;

use Database\Factories\WorkflowStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStage extends Model
{
    /** @use HasFactory<WorkflowStageFactory> */
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'sequence',
        'code',
        'name',
        'description',
        'applies_to',
        'sla_days',
        'is_active',
        'is_skippable',
        'required_permission',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_skippable' => 'boolean',
        ];
    }
}
