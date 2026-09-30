<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cnic',
        'cnic_pending',
        'full_name',
        'normalized_name',
        'father_name',
        'gender',
        'date_of_birth',
        'mobile',
        'alt_mobile',
        'email',
        'address',
        'photo_path',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cnic_pending' => 'boolean',
            'date_of_birth' => 'date',
        ];
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(PersonQualification::class);
    }

    public function companyPeople(): HasMany
    {
        return $this->hasMany(CompanyPerson::class);
    }

    public function dealerOwners(): HasMany
    {
        return $this->hasMany(DealerOwner::class);
    }
}
