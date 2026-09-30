<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleCompany;
use Database\Factories\CompanyPersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyPerson extends Model
{
    /** @use HasFactory<CompanyPersonFactory> */
    use BelongsToVisibleCompany, HasFactory, SoftDeletes;

    protected $table = 'company_people';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'person_id',
        'role',
        'designation',
        'appointment_date',
        'start_date',
        'end_date',
        'end_reason',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'source',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
