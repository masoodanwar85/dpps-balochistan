<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAsset extends Model
{
    use BelongsToVisibleCompany;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'asset_type',
        'description',
        'district_id',
        'estimated_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
