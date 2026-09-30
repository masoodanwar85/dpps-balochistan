<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the people company product and dealer tables', function () {
    foreach ([
        'persons',
        'person_qualifications',
        'companies',
        'company_people',
        'company_premises',
        'company_assets',
        'products',
        'company_products',
        'dealers',
        'dealer_owners',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    $companyForeignKey = collect(Schema::getForeignKeys('users'))
        ->first(fn (array $foreignKey): bool => in_array('company_id', $foreignKey['columns'], true));

    expect($companyForeignKey)->not->toBeNull()
        ->and($companyForeignKey['foreign_table'])->toBe('companies');

    expect(Schema::hasColumn('products', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('company_premises', 'created_by'))->toBeFalse()
        ->and(Schema::hasColumn('persons', 'deleted_at'))->toBeTrue();
});

it('keeps one active technical staff assignment per person', function () {
    $userId = User::factory()->create()->id;
    $provinceId = DB::table('provinces')->insertGetId([
        'name' => 'Balochistan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $personId = DB::table('persons')->insertGetId([
        'cnic' => '1234567890123',
        'full_name' => 'Ali Khan',
        'normalized_name' => 'ali khan',
        'mobile' => '03001234567',
        'created_by' => $userId,
        'updated_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $firstCompanyId = insertCompany($userId, $provinceId, 'C-0001', 'Alpha Pesticides');
    $secondCompanyId = insertCompany($userId, $provinceId, 'C-0002', 'Beta Pesticides');

    $firstAssignmentId = DB::table('company_people')->insertGetId(
        technicalStaff($firstCompanyId, $personId, $userId)
    );

    expect(DB::table('company_people')->where('id', $firstAssignmentId)->value('active_tech_person'))
        ->toEqual($personId);

    expect(fn () => DB::table('company_people')->insert(
        technicalStaff($secondCompanyId, $personId, $userId)
    ))->toThrow(UniqueConstraintViolationException::class);

    DB::table('company_people')->where('id', $firstAssignmentId)->update([
        'end_date' => '2024-06-01',
    ]);

    expect(DB::table('company_people')->where('id', $firstAssignmentId)->value('active_tech_person'))
        ->toBeNull();

    DB::table('company_people')->insert(
        technicalStaff($secondCompanyId, $personId, $userId)
    );

    expect(DB::table('company_people')->where('role', 'technical_staff')->count())->toBe(2);

    expect(fn () => DB::table('company_people')->insert([
        ...technicalStaff($firstCompanyId, $personId, $userId),
        'role' => 'director',
        'start_date' => '2024-01-01',
        'end_date' => '2023-12-31',
    ]))->toThrow(QueryException::class);
});

function insertCompany(int $userId, int $provinceId, string $code, string $name): int
{
    return DB::table('companies')->insertGetId([
        'company_code' => $code,
        'name' => $name,
        'normalized_name' => strtolower($name),
        'legal_type' => 'private_ltd',
        'head_office_address' => 'Quetta',
        'city' => 'Quetta',
        'province_id' => $provinceId,
        'status' => 'unlicensed',
        'created_by' => $userId,
        'updated_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function technicalStaff(int $companyId, int $personId, int $userId): array
{
    return [
        'company_id' => $companyId,
        'person_id' => $personId,
        'role' => 'technical_staff',
        'start_date' => '2024-01-01',
        'verification_status' => 'pending',
        'source' => 'office',
        'created_by' => $userId,
        'updated_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
