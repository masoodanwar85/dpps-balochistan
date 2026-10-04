<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyCsrRndFile;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::persistentFake('local', ['serve' => true]);
});

function csrRndUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'must_change_password' => false,
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

function csrRndPdf(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

    return new UploadedFile($path, 'csr.pdf', null, null, true);
}

it('saves csr and rnd flags on the company and stores separate media files', function () {
    $officer = csrRndUser('Data Entry Operator');
    $provinceId = Province::factory()->create()->id;

    $created = $this->actingAs($officer, 'sanctum')->postJson('/api/v1/companies', [
        'name' => 'CSR Agro',
        'legal_type' => 'private_ltd',
        'head_office_address' => 'Jinnah Road',
        'city' => 'Quetta',
        'province_id' => $provinceId,
        'pcpa_member' => false,
        'croplife_member' => false,
        'csr' => true,
        'rnd' => true,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.csr', true)
        ->assertJsonPath('data.rnd', true);

    $companyId = $created->json('data.id');

    $upload = $this->actingAs($officer, 'sanctum')
        ->post("/api/v1/companies/{$companyId}/csr-rnd", [
            'kind' => 'csr',
            'title' => 'Farm visit',
            'file' => csrRndPdf(),
        ]);

    $upload->assertCreated()
        ->assertJsonPath('data.kind', 'csr')
        ->assertJsonPath('data.title', 'Farm visit');

    expect(CompanyCsrRndFile::query()->where('company_id', $companyId)->count())->toBe(1)
        ->and(ActivityLog::query()->where('subject_type', 'company_csr_rnd_file')->where('action', 'created')->exists())->toBeTrue();

    $list = $this->actingAs($officer, 'sanctum')->getJson("/api/v1/companies/{$companyId}/csr-rnd");
    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.kinds.0.value', 'csr');

    $this->actingAs($officer, 'sanctum')
        ->putJson("/api/v1/companies/{$companyId}", [
            'name' => 'CSR Agro',
            'legal_type' => 'private_ltd',
            'head_office_address' => 'Jinnah Road',
            'city' => 'Quetta',
            'province_id' => $provinceId,
            'pcpa_member' => false,
            'croplife_member' => false,
            'csr' => false,
            'rnd' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.csr', false);

    expect(CompanyCsrRndFile::query()->where('company_id', $companyId)->count())->toBe(1);

    $this->actingAs($officer, 'sanctum')
        ->post("/api/v1/companies/{$companyId}/csr-rnd", [
            'kind' => 'csr',
            'title' => 'Should fail',
            'file' => csrRndPdf(),
        ])
        ->assertUnprocessable();
});

it('lets a company user upload csr rnd files for their own company only', function () {
    $company = Company::factory()->create(['csr' => true, 'rnd' => false]);
    $other = Company::factory()->create(['csr' => true, 'rnd' => true]);
    $admin = csrRndUser('Company Admin', [
        'user_type' => 'company',
        'company_id' => $company->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/portal/csr-rnd')
        ->assertOk()
        ->assertJsonCount(1, 'meta.kinds')
        ->assertJsonPath('meta.kinds.0.value', 'csr');

    $this->actingAs($admin, 'sanctum')
        ->post('/api/v1/portal/csr-rnd', [
            'kind' => 'rnd',
            'title' => 'Lab notes',
            'file' => csrRndPdf(),
        ])
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->post('/api/v1/portal/csr-rnd', [
            'kind' => 'csr',
            'title' => 'Community day',
            'file' => csrRndPdf(),
        ])
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->post("/api/v1/companies/{$other->id}/csr-rnd", [
            'kind' => 'csr',
            'title' => 'Other company',
            'file' => csrRndPdf(),
        ])
        ->assertNotFound();
});
