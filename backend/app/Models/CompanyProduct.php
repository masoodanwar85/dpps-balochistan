<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyProduct extends Model
{
    use BelongsToVisibleCompany, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'product_id',
        'brand_name',
        'dpp_registration_no',
        'dpp_valid_to',
        'source',
        'sample_provided',
        'status',
        'approved_in_license_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dpp_valid_to' => 'date',
            'sample_provided' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
