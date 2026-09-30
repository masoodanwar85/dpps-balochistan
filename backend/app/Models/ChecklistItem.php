<?php

namespace App\Models;

use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'template_id',
        'sort_order',
        'annex_code',
        'title',
        'description',
        'form_reference',
        'is_required',
        'requires_upload',
        'allowed_file_types',
        'max_files',
        'attestation_required',
        'requires_validity_dates',
        'must_cover_license_period',
        'portal_uploadable',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'requires_upload' => 'boolean',
            'requires_validity_dates' => 'boolean',
            'must_cover_license_period' => 'boolean',
            'portal_uploadable' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
