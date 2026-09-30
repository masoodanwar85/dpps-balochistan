<?php

namespace App\Models;

use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'is_agriculture_degree',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_agriculture_degree' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
