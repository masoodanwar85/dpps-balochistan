<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPremise extends Model
{
    use BelongsToVisibleCompany;

    protected $table = 'company_premises';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'type',
        'district_id',
        'address',
        'gps_lat',
        'gps_lng',
        'contact_person_id',
        'phone',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'is_active' => 'boolean',
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

    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contact_person_id');
    }
}
