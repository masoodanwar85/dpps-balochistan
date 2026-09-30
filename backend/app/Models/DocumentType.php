<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'applies_to',
        'has_expiry',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
