<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'generic_name',
        'market_name',
        'concentration',
        'formulation',
        'category',
        'is_restricted',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_restricted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function displayName(): string
    {
        return $this->market_name.' ('.$this->generic_name.')';
    }

    public function catalogLabel(): string
    {
        return $this->displayName().' '.$this->concentration.' '.$this->formulation;
    }
}
