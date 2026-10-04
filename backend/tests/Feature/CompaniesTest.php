<?php

use App\Exports\CompaniesExport;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\License;
use App\Models\Province;
use App\Models\User;
use App\Services\Companies\CompanyName;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function companyActor(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'must_change_password' => false,
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

function companyBody(int $provinceId, array $overrides = []): array
{
    return array_merge([
        'name' => 'New Agro',
        'legal_type' => 'private_ltd',
        'ntn' => null,
        'head_office_address' => 'Jinnah Road',
        'city' => 'Quetta',
        'province_id' => $provinceId,
        'pcpa_member' => false,
        'croplife_member' => false,
        'csr' => false,
        'rnd' => false,
    ], $overrides);
}

it('blocks a duplicate NTN and allows more than one empty NTN (R-11)', function () {
    $officer = companyActor('Data Entry Operator');
    $provinceId = Province::factory()->create()->id;

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, ['name' => 'First Agro', 'ntn' => '1234567-8']))
        ->assertCreated();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, ['name' => 'Second Agro', 'ntn' => null]))
        ->assertCreated();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, ['name' => 'Third Agro', 'ntn' => null]))
        ->assertCreated();

    $duplicate = $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'Fourth Agro',
            'ntn' => '1234567-8',
        ]));

    $duplicate->assertUnprocessable()
        ->assertJsonPath('errors.ntn.0', 'This NTN already exists.');

    $preview = $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies/check-duplicate', [
            'name' => 'Fourth Agro',
            'ntn' => '1234567-8',
        ]);

    $preview->assertOk()->assertJsonPath('data.ntn_taken', true);
});

it('warns on a similar company name and logs the reason (R-12)', function () {
    $officer = companyActor('Registration Officer');
    $provinceId = Province::factory()->create()->id;
    $names = app(CompanyName::class);

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'A.M.B. Agro (Pvt) Ltd',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.company_code', 'C-0001');

    expect($names->normalize('A.M.B. Agro (Pvt) Ltd'))->toBe('amb agro')
        ->and($names->normalize('AMB Agro'))->toBe('amb agro');

    $warned = $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'AMB Agro',
        ]));

    $warned->assertStatus(409)
        ->assertJsonPath('warnings.0', 'Similar existing company: "A.M.B. Agro (Pvt) Ltd" (C-0001).');

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'Northern Agro Services',
        ]))
        ->assertCreated();

    $contained = $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'Agro Services',
        ]));

    $contained->assertStatus(409);
    expect($contained->json('warnings.0'))->toContain('Northern Agro Services');

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'Quetta Pesticide House',
        ]))
        ->assertCreated();

    similar_text('quetta pesticide house', 'quetta pesticides house', $percent);
    expect($percent)->toBeGreaterThanOrEqual(85);
    expect($names->similar('Quetta Pesticides House'))->not->toBeEmpty();

    $saved = $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'AMB Agro',
            'confirm_warnings' => true,
            'warning_reason' => 'Different directors',
        ]));

    $saved->assertCreated()->assertJsonPath('data.company_code', 'C-0004');

    $log = ActivityLog::query()->where('action', 'warning_overridden')->where('subject_type', 'company')->first();
    expect($log)->not->toBeNull()
        ->and($log->new_values['warning_reason'])->toBe('Different directors');
});

it('keeps company users on their own company and officers on the full list', function () {
    $provinceId = Province::factory()->create()->id;
    $own = Company::factory()->create(['name' => 'Own Agro', 'province_id' => $provinceId]);
    $other = Company::factory()->create(['name' => 'Other Agro', 'province_id' => $provinceId]);
    $admin = companyActor('Company Admin', [
        'user_type' => 'company',
        'company_id' => $own->id,
    ]);
    $officer = companyActor('District Officer');
    $entry = companyActor('Data Entry Operator');

    $list = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/companies');
    $list->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $own->id);

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/companies/'.$other->id)->assertNotFound();
    $this->actingAs($officer, 'sanctum')->getJson('/api/v1/companies')->assertOk()->assertJsonPath('meta.total', 2);
    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, ['name' => 'Blocked Agro']))
        ->assertForbidden();
    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, [
            'name' => 'Entered Agro',
            'website' => 'https://example.com',
            'pcpa_member' => true,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'unlicensed')
        ->assertJsonPath('data.website', 'https://example.com')
        ->assertJsonPath('data.company_code', 'C-0001');
});

it('filters by PCPA and by the current license expiry', function () {
    $officer = companyActor('Director');
    $member = Company::factory()->create(['name' => 'Member Agro', 'pcpa_member' => true]);
    $expired = Company::factory()->create(['name' => 'Expired Agro']);
    $expiring = Company::factory()->create(['name' => 'Expiring Agro']);
    $actorId = $officer->id;

    License::query()->create([
        'license_no' => 'DPP/C/2024/0001',
        'licensable_type' => 'company',
        'licensable_id' => $expired->id,
        'license_kind' => 'registration',
        'valid_from' => Carbon::today()->subYear()->toDateString(),
        'valid_to' => Carbon::today()->subDay()->toDateString(),
        'status' => 'active',
        'verification_token' => str_repeat('a', 40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
    License::query()->create([
        'license_no' => 'DPP/C/2025/0001',
        'licensable_type' => 'company',
        'licensable_id' => $expiring->id,
        'license_kind' => 'registration',
        'valid_from' => Carbon::today()->subMonth()->toDateString(),
        'valid_to' => Carbon::today()->addDays(10)->toDateString(),
        'status' => 'active',
        'verification_token' => str_repeat('b', 40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/companies?filter[pcpa_member]=true')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $member->id);

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/companies?filter[expiry]=expired')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $expired->id)
        ->assertJsonPath('data.0.expiry_date', Carbon::today()->subDay()->toDateString());

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/companies?filter[expiry]=expiring')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $expiring->id);
});

it('exports the filtered list for an export user and soft-deletes with a reason', function () {
    Excel::fake();
    $admin = companyActor('Super Admin');
    $auditor = companyActor('Auditor');
    $entry = companyActor('Data Entry Operator');
    $provinceId = Province::factory()->create()->id;

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/companies', companyBody($provinceId, ['name' => 'Export Agro', 'ntn' => '999']));
    $created->assertCreated();
    $companyId = $created->json('data.id');

    $this->actingAs($entry, 'sanctum')->get('/api/v1/exports/companies')->assertForbidden();
    $this->actingAs($auditor, 'sanctum')->putJson('/api/v1/companies/'.$companyId, companyBody($provinceId, [
        'name' => 'Changed Agro',
    ]))->assertForbidden();

    $this->actingAs($auditor, 'sanctum')->get('/api/v1/exports/companies?search=Export');

    Excel::assertDownloaded('companies.xlsx', function (CompaniesExport $export) {
        return $export->collection()->contains(fn (array $row) => $row[1] === 'Export Agro');
    });

    expect(ActivityLog::query()->where('action', 'exported')->where('subject_type', 'company')->count())->toBe(1);

    $this->actingAs($auditor, 'sanctum')
        ->deleteJson('/api/v1/companies/'.$companyId, ['reason' => 'Entered twice'])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/companies/'.$companyId, [])
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/companies/'.$companyId, ['reason' => 'Entered twice'])
        ->assertOk();

    expect(Company::query()->find($companyId))->toBeNull()
        ->and(Company::withTrashed()->find($companyId))->not->toBeNull();

    $deleted = ActivityLog::query()->where('action', 'deleted')->where('subject_id', $companyId)->first();
    expect($deleted->new_values['reason'])->toBe('Entered twice');
});
