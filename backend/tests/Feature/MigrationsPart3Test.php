<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the workflow application license document and log tables', function () {
    foreach ([
        'workflow_stages',
        'checklist_templates',
        'checklist_items',
        'license_applications',
        'application_stage_logs',
        'application_checklist_items',
        'application_penalties',
        'deficiency_letters',
        'deficiency_letter_items',
        'challans',
        'challan_items',
        'licenses',
        'license_status_history',
        'license_number_sequences',
        'documents',
        'notifications',
        'activity_logs',
        'import_batches',
        'import_exceptions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumn('activity_logs', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('activity_logs', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('license_applications', 'open_flag'))->toBeTrue();

    expect(foreignKeyTargets('person_qualifications', 'degree_document_id'))->toBe('documents')
        ->and(foreignKeyTargets('company_products', 'approved_in_license_id'))->toBe('licenses')
        ->and(foreignKeyTargets('license_applications', 'previous_license_id'))->toBe('licenses')
        ->and(foreignKeyTargets('challans', 'document_id'))->toBe('documents');
});

it('allows only one open application for the same company or dealer', function () {
    $userId = User::factory()->create()->id;
    $provinceId = DB::table('provinces')->insertGetId([
        'name' => 'Balochistan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $companyId = DB::table('companies')->insertGetId([
        'company_code' => 'C-0001',
        'name' => 'Alpha Pesticides',
        'normalized_name' => 'alpha pesticides',
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
    $templateId = DB::table('checklist_templates')->insertGetId([
        'entity_type' => 'company',
        'application_type' => 'new',
        'version_no' => 1,
        'name' => 'Company new',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $firstId = DB::table('license_applications')->insertGetId(
        applicationRow($userId, $templateId, $companyId, 'APP-C-2026-0001', 'draft')
    );

    expect(DB::table('license_applications')->where('id', $firstId)->value('open_flag'))->toEqual(1);

    expect(fn () => DB::table('license_applications')->insert(
        applicationRow($userId, $templateId, $companyId, 'APP-C-2026-0002', 'submitted')
    ))->toThrow(UniqueConstraintViolationException::class);

    DB::table('license_applications')->where('id', $firstId)->update(['status' => 'issued']);

    expect(DB::table('license_applications')->where('id', $firstId)->value('open_flag'))->toBeNull();

    DB::table('license_applications')->insert(
        applicationRow($userId, $templateId, $companyId, 'APP-C-2026-0002', 'draft')
    );

    $penaltyId = DB::table('application_penalties')->insertGetId([
        'application_id' => $firstId,
        'penalty_type' => 'late_renewal',
        'basis' => '12 days x 500',
        'standard_amount' => 6000,
        'final_amount' => 3000,
        'entered_by' => $userId,
        'entered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('application_penalties')->where('id', $penaltyId)->value('is_waived_or_reduced'))->toEqual(1);

    expect(fn () => DB::table('licenses')->insert([
        'license_no' => 'DPP/C/2026/0001',
        'licensable_type' => 'company',
        'licensable_id' => $companyId,
        'license_kind' => 'registration',
        'valid_from' => '2026-02-01',
        'valid_to' => '2026-01-01',
        'status' => 'active',
        'verification_token' => str_repeat('a', 40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'is_legacy' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

function foreignKeyTargets(string $table, string $column): ?string
{
    $foreignKey = collect(Schema::getForeignKeys($table))
        ->first(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true));

    return $foreignKey['foreign_table'] ?? null;
}

/**
 * @return array<string, mixed>
 */
function applicationRow(int $userId, int $templateId, int $companyId, string $number, string $status): array
{
    return [
        'application_no' => $number,
        'licensable_type' => 'company',
        'licensable_id' => $companyId,
        'application_type' => 'new',
        'checklist_template_id' => $templateId,
        'submitted_via' => 'office',
        'submitted_by_user_id' => $userId,
        'status' => $status,
        'fee_amount' => 0,
        'penalty_total' => 0,
        'total_payable' => 0,
        'created_by' => $userId,
        'updated_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
