<?php

use App\Models\ActivityLog;
use App\Models\District;
use App\Models\FeeStructure;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\NumberPattern;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function settingsUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole($role);

    return $user;
}

it('returns only editable settings and a license number preview', function () {
    $director = settingsUser('Director');

    $this->actingAs(settingsUser('Registration Officer'), 'sanctum')
        ->getJson('/api/v1/settings')
        ->assertForbidden();

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/settings');

    $response->assertOk();
    $keys = collect($response->json('data'))->pluck('key');

    expect($keys)->not->toContain('public_verify_base_url')
        ->and($keys)->toContain('enforce_document_requirements');

    $company = collect($response->json('data'))->firstWhere('key', 'company_license_no_pattern');
    $application = collect($response->json('data'))->firstWhere('key', 'application_no_pattern');

    expect($company['value'])->toBe('DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}')
        ->and($company['preview'])->toBe('DPP/C/'.now()->year.'/0046/R1')
        ->and($application['preview'])->toBe('APP-C-'.now()->year.'-0046')
        ->and(collect($response->json('data'))->firstWhere('key', 'enforce_document_requirements')['value'])->toBeFalse();
});

it('updates editable settings and records each change', function () {
    $admin = settingsUser('Super Admin');

    $this->actingAs(settingsUser('Data Entry Operator'), 'sanctum')
        ->putJson('/api/v1/settings', [
            'settings' => ['enforce_document_requirements' => true],
        ])->assertForbidden();

    $rejected = $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings', [
            'settings' => [
                'enforce_document_requirements' => true,
                'renewal_window_days' => 45,
                'public_verify_base_url' => 'https://example.test/verify/',
            ],
        ]);

    $rejected->assertUnprocessable();
    expect($rejected->json('errors')['settings.public_verify_base_url'][0])
        ->toBe('This setting cannot be changed from the screen.');

    expect(Setting::query()->where('key', 'enforce_document_requirements')->value('value'))->toBe('false')
        ->and(Setting::query()->where('key', 'public_verify_base_url')->value('value'))->toBe('https://<domain>/verify/');

    $updated = $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings', [
            'settings' => [
                'enforce_document_requirements' => true,
                'renewal_window_days' => 45,
                'company_license_no_pattern' => 'DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}',
            ],
        ]);

    $updated->assertOk()
        ->assertJsonPath('data.0.key', 'company_license_period_months');

    expect(Setting::query()->where('key', 'enforce_document_requirements')->value('value'))->toBe('true')
        ->and(Setting::query()->where('key', 'renewal_window_days')->value('value'))->toBe('45')
        ->and(Setting::query()->where('key', 'company_license_period_months')->value('value'))->toBe('12');

    $logs = ActivityLog::query()->where('subject_type', 'setting')->where('action', 'updated')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('batch_uuid')->unique())->toHaveCount(1);

    $flag = $logs->first(fn (ActivityLog $log) => str_contains($log->description, 'enforce_document_requirements'));
    expect($flag->old_values)->toBe(['value' => 'false'])
        ->and($flag->new_values)->toBe(['value' => 'true']);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings', [
            'settings' => ['company_license_no_pattern' => 'DPP/{NOT_A_TOKEN}'],
        ])->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings', [
            'settings' => ['renewal_window_days' => 45],
        ])->assertOk();

    expect(ActivityLog::query()->where('subject_type', 'setting')->count())->toBe(2);
    expect(NumberPattern::preview('DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}{RENEWAL}'))
        ->toBe('DPP/D/QTA/'.now()->year.'/0046/R1');
});

it('manages districts and tehsils for lookup managers only', function () {
    $admin = settingsUser('Super Admin');
    $officer = settingsUser('District Officer');

    $this->actingAs($officer, 'sanctum')->getJson('/api/v1/districts')->assertForbidden();
    $this->actingAs($officer, 'sanctum')->postJson('/api/v1/provinces', [
        'name' => 'Blocked',
        'is_active' => true,
    ])->assertForbidden();

    $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/districts', [
        'name' => 'Screen District',
        'code' => 'sdx',
        'is_active' => true,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.code', 'SDX');

    $districtId = $created->json('data.id');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/districts', [
        'name' => 'Another',
        'code' => 'sdx',
        'is_active' => true,
    ])->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/districts/'.$districtId, [
        'name' => 'Screen District',
        'code' => 'SDX',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    expect(ActivityLog::query()->where('subject_type', 'district')->where('action', 'created')->count())->toBe(1)
        ->and(ActivityLog::query()->where('subject_type', 'district')->where('action', 'updated')->count())->toBe(1);

    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $hub = District::factory()->create(['name' => 'Hub', 'code' => 'HUB']);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/tehsils', [
        'district_id' => $quetta->id,
        'name' => 'Sariab',
        'is_active' => true,
    ])->assertCreated();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/tehsils', [
        'district_id' => $quetta->id,
        'name' => 'Sariab',
        'is_active' => true,
    ])->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/tehsils', [
        'district_id' => $hub->id,
        'name' => 'Sariab',
        'is_active' => true,
    ])->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/districts?search=Screen&filter[is_active]=0')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'SDX')
        ->assertJsonPath('meta.total', 1);
});

it('creates provinces, qualifications and document types', function () {
    $admin = settingsUser('Super Admin');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/provinces', [
        'name' => 'Balochistan',
        'is_active' => true,
    ])->assertCreated();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/provinces', [
        'name' => 'Balochistan',
        'is_active' => true,
    ])->assertUnprocessable();

    $qualification = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/qualifications', [
        'name' => 'B.Sc Agriculture',
        'is_agriculture_degree' => true,
        'is_active' => true,
    ]);

    $qualification->assertCreated()->assertJsonPath('data.is_agriculture_degree', true);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/document-types', [
        'name' => 'CNIC copy',
        'category' => 'cnic',
        'applies_to' => 'person',
        'has_expiry' => true,
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('data.category', 'cnic');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/document-types', [
        'name' => 'Bad category',
        'category' => 'photo',
        'applies_to' => 'person',
        'has_expiry' => false,
        'is_active' => true,
    ])->assertUnprocessable();
});

it('keeps the products master separate from company product permission', function () {
    $director = settingsUser('Director');
    $entry = settingsUser('Data Entry Operator');

    $this->actingAs($entry, 'sanctum')->getJson('/api/v1/products')->assertForbidden();

    $created = $this->actingAs($director, 'sanctum')->postJson('/api/v1/products', [
        'generic_name' => 'Chlorpyrifos',
        'market_name' => 'Chlorpyrifos Market',
        'concentration' => '40%',
        'formulation' => 'EC',
        'category' => 'insecticide',
        'is_restricted' => false,
        'is_active' => true,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.generic_name', 'Chlorpyrifos')
        ->assertJsonPath('data.market_name', 'Chlorpyrifos Market')
        ->assertJsonPath('data.display_name', 'Chlorpyrifos Market (Chlorpyrifos)');
    $productId = $created->json('data.id');

    $this->actingAs($director, 'sanctum')->postJson('/api/v1/products', [
        'generic_name' => 'Chlorpyrifos',
        'market_name' => 'Chlorpyrifos Market',
        'concentration' => '40%',
        'formulation' => 'EC',
        'category' => 'insecticide',
        'is_restricted' => true,
        'is_active' => true,
    ])->assertUnprocessable();

    $this->actingAs($director, 'sanctum')->postJson('/api/v1/products', [
        'generic_name' => 'Chlorpyrifos',
        'market_name' => 'Chlorpyrifos WP Market',
        'concentration' => '40%',
        'formulation' => 'WP',
        'category' => 'insecticide',
        'is_restricted' => false,
        'is_active' => true,
    ])->assertCreated();

    $this->actingAs($director, 'sanctum')->postJson('/api/v1/products', [
        'generic_name' => 'Imidacloprid',
        'market_name' => 'Chlorpyrifos Market',
        'concentration' => '20%',
        'formulation' => 'SL',
        'category' => 'insecticide',
        'is_restricted' => false,
        'is_active' => true,
    ])->assertUnprocessable();

    $this->actingAs($director, 'sanctum')->putJson('/api/v1/products/'.$productId, [
        'generic_name' => 'Chlorpyrifos',
        'market_name' => 'Chlorpyrifos Market',
        'concentration' => '40%',
        'formulation' => 'EC',
        'category' => 'insecticide',
        'is_restricted' => true,
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_restricted', true);

    expect(Product::query()->find($productId)->is_active)->toBeFalse()
        ->and(ActivityLog::query()->where('subject_type', 'product')->where('action', 'created')->count())->toBe(2);
});

it('lets a settings manager set registration and renewal fees and closes the previous period', function () {
    $admin = settingsUser('Super Admin');
    $entry = settingsUser('Data Entry Operator');

    $this->actingAs($entry, 'sanctum')->getJson('/api/v1/fees')->assertForbidden();

    FeeStructure::query()->create([
        'entity_type' => 'company',
        'fee_type' => 'registration',
        'amount' => 50000,
        'effective_from' => '2020-01-01',
        'effective_to' => null,
        'notes' => 'Old rate',
    ]);

    $list = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/fees');
    $list->assertOk()->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.label', 'Company registration')
        ->assertJsonPath('data.0.current.amount', '50000.00');

    $saved = $this->actingAs($admin, 'sanctum')->putJson('/api/v1/fees', [
        'fees' => [
            [
                'entity_type' => 'company',
                'fee_type' => 'registration',
                'amount' => 55000,
                'effective_from' => '2026-10-01',
            ],
            [
                'entity_type' => 'company',
                'fee_type' => 'renewal',
                'amount' => 30000,
                'effective_from' => '2026-10-01',
            ],
            [
                'entity_type' => 'dealer',
                'fee_type' => 'registration',
                'amount' => 5000,
                'effective_from' => '2026-10-01',
            ],
            [
                'entity_type' => 'dealer',
                'fee_type' => 'renewal',
                'amount' => 2500,
                'effective_from' => '2026-10-01',
            ],
        ],
    ]);

    $saved->assertOk()
        ->assertJsonPath('data.0.current.amount', '55000.00')
        ->assertJsonPath('data.0.current.effective_from', '2026-10-01');

    $closed = FeeStructure::query()
        ->where('entity_type', 'company')
        ->where('fee_type', 'registration')
        ->where('amount', 50000)
        ->first();

    expect($closed?->effective_to?->toDateString())->toBe('2026-09-30')
        ->and((float) FeeStructure::query()->whereNull('effective_to')->where('entity_type', 'company')->where('fee_type', 'registration')->value('amount'))
        ->toBe(55000.0)
        ->and(ActivityLog::query()->where('subject_type', 'fee_structure')->where('action', 'created')->count())->toBeGreaterThan(0);

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/fees', [
        'fees' => [[
            'entity_type' => 'company',
            'fee_type' => 'registration',
            'amount' => 56000,
            'effective_from' => '2026-10-01',
        ]],
    ])->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/fees', [
        'fees' => [[
            'entity_type' => 'company',
            'fee_type' => 'late_renewal_per_day',
            'amount' => 500,
            'effective_from' => Carbon::today()->toDateString(),
        ]],
    ])->assertUnprocessable();
});
