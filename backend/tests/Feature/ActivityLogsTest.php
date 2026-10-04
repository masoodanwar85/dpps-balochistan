<?php

use App\Exports\ActivityLogsExport;
use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function activityUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'must_change_password' => false,
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

function activityCompany(int $provinceId, array $overrides = []): array
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

it('shows old and new values and every R-32 action to an auditor', function () {
    Excel::fake();
    Storage::persistentFake('local', ['serve' => true]);

    $admin = activityUser('Super Admin', [
        'name' => 'Log Admin',
        'password' => 'Password1',
    ]);
    $auditor = activityUser('Auditor', ['name' => 'Log Auditor']);
    $entry = activityUser('Data Entry Operator');
    $officer = activityUser('Registration Officer');
    $provinceId = Province::factory()->create()->id;
    $companyUser = activityUser('Company Admin', [
        'user_type' => 'company',
        'company_id' => null,
    ]);

    $this->withHeader('Origin', 'http://localhost:5173')
        ->withHeader('Referer', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password1',
        ])
        ->assertOk();
    auth()->logout();
    $this->flushHeaders();

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/companies', activityCompany($provinceId, [
            'name' => 'A.M.B. Agro (Pvt) Ltd',
        ]));
    $created->assertCreated();
    $companyId = $created->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/companies', activityCompany($provinceId, [
            'name' => 'AMB Agro',
        ]))
        ->assertStatus(409);

    $warned = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/companies', activityCompany($provinceId, [
            'name' => 'AMB Agro',
            'confirm_warnings' => true,
            'warning_reason' => 'Same trading name',
        ]));
    $warned->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/companies/'.$companyId, activityCompany($provinceId, [
            'name' => 'Logbook Holdings',
        ]))
        ->assertOk();

    $staff = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/companies/'.$companyId.'/people', [
            'role' => 'technical_staff',
            'cnic' => '5440088800001',
            'full_name' => 'Naveed Logbook',
            'father_name' => 'Ahmed Khan',
            'mobile' => '03008880001',
            'start_date' => Carbon::today()->toDateString(),
        ]);
    $staff->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/company-people/'.$staff->json('data.id').'/verify')
        ->assertOk();

    $type = DocumentType::factory()->create([
        'name' => 'Activity letter type',
        'applies_to' => 'company',
    ]);
    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    $uploaded = $this->actingAs($admin, 'sanctum')
        ->post('/api/v1/companies/'.$companyId.'/documents', [
            'file' => new UploadedFile($path, 'letter.pdf', null, null, true),
            'document_type_id' => $type->id,
            'title' => 'Activity letter',
            'attested_by' => 'none',
        ]);
    $uploaded->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/documents/'.$uploaded->json('data.id').'/download-url')
        ->assertOk();

    $this->actingAs($admin, 'sanctum')->get('/api/v1/exports/companies')->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/companies/'.$warned->json('data.id'), ['reason' => 'Entered twice'])
        ->assertOk();

    ActivityLog::query()->create([
        'user_id' => $admin->id,
        'user_type' => 'staff',
        'action' => 'updated',
        'subject_type' => 'user',
        'subject_id' => $admin->id,
        'description' => 'Password row',
        'old_values' => ['password' => 'secret-old', 'name' => 'Before'],
        'new_values' => ['password' => 'secret-new', 'name' => 'After'],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'channel' => 'web',
        'route' => '/test',
        'batch_uuid' => '11111111-1111-1111-1111-111111111111',
        'created_at' => now(),
    ]);

    foreach (['created', 'updated', 'deleted', 'login', 'downloaded', 'exported', 'approved', 'warning_overridden'] as $action) {
        $listed = $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/v1/activity-logs?action='.$action);

        $listed->assertOk();
        expect($listed->json('meta.total'))->toBeGreaterThan(0)
            ->and(collect($listed->json('data'))->pluck('action')->unique()->all())->toBe([$action]);
    }

    $updated = $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/activity-logs?action=updated&module=company&user_id='.$admin->id.'&from='.Carbon::today()->toDateString().'&to='.Carbon::today()->toDateString());
    $updated->assertOk();
    $companyUpdate = collect($updated->json('data'))->first(
        fn (array $row) => ($row['old_values']['name'] ?? null) === 'A.M.B. Agro (Pvt) Ltd'
    );
    expect($companyUpdate)->not->toBeNull()
        ->and($companyUpdate['new_values']['name'])->toBe('Logbook Holdings')
        ->and($companyUpdate['user_name'])->toBe('Log Admin')
        ->and(collect($companyUpdate['changes'])->firstWhere('field', 'name')['old'])->toBe('A.M.B. Agro (Pvt) Ltd')
        ->and(collect($companyUpdate['changes'])->firstWhere('field', 'name')['new'])->toBe('Logbook Holdings');

    $override = $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/activity-logs?action=warning_overridden');
    expect($override->json('data.0.reason'))->toBe('Same trading name')
        ->and($override->json('data.0.new_values'))->not->toHaveKey('warning_reason');

    $page = $this->actingAs($auditor, 'sanctum')->getJson('/api/v1/activity-logs');
    $page->assertOk()
        ->assertJsonPath('meta.per_page', 25);
    expect($page->getContent())->not->toContain('secret-old')
        ->and($page->getContent())->not->toContain('secret-new')
        ->and(collect($page->json('meta.users'))->pluck('name')->all())->toContain('Log Admin')
        ->and($page->json('meta.actions'))->toContain('login')
        ->and($page->json('meta.modules'))->toContain('company');

    $passwordRow = collect($page->json('data'))->firstWhere('record', 'Password row');
    expect($passwordRow['old_values'])->not->toHaveKey('password')
        ->and($passwordRow['new_values']['name'])->toBe('After');

    $yesterday = Carbon::yesterday()->toDateString();
    $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/activity-logs?from='.$yesterday.'&to='.$yesterday)
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/activity-logs?user_id=system')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/activity-logs?from=2026-09-28&to=2026-09-01')
        ->assertUnprocessable();

    $this->actingAs($entry, 'sanctum')->getJson('/api/v1/activity-logs')->assertForbidden();
    $this->actingAs($companyUser, 'sanctum')->getJson('/api/v1/activity-logs')->assertForbidden();
    $this->actingAs($officer, 'sanctum')->get('/api/v1/exports/activity-logs')->assertForbidden();

    $this->actingAs($auditor, 'sanctum')
        ->get('/api/v1/exports/activity-logs?action=login');

    Excel::assertDownloaded('activity-logs.xlsx', function (ActivityLogsExport $export) {
        $rows = $export->collection();

        return $rows->isNotEmpty()
            && $rows->every(fn (array $row) => $row[2] === 'login')
            && $rows->contains(fn (array $row) => $row[1] === 'Log Admin' && str_contains($row[3], 'Logged in'));
    });

    expect(ActivityLog::query()->where('action', 'exported')->where('subject_type', 'activity_log')->count())->toBe(1);
});
