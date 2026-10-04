<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\DealerOwner;
use App\Models\District;
use App\Models\License;
use App\Models\Person;
use App\Models\Product;
use App\Models\Qualification;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function staffBody(array $overrides = []): array
{
    return array_merge([
        'role' => 'technical_staff',
        'cnic' => '5440011111111',
        'full_name' => 'Naveed Ahmed',
        'father_name' => 'Ahmed Khan',
        'mobile' => '03001230001',
        'start_date' => Carbon::today()->toDateString(),
    ], $overrides);
}

it('links an existing person and creates a new one CNIC-first (R-06)', function () {
    $officer = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    $existing = Person::factory()->create([
        'cnic' => '5440011111111',
        'full_name' => 'Naveed Ahmed',
        'mobile' => '03001230009',
    ]);
    $qualification = Qualification::factory()->create(['name' => 'B.Sc Agri']);

    $linked = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'full_name' => 'Someone Else',
            'mobile' => '03009990000',
            'qualification_id' => $qualification->id,
            'institution' => 'Agriculture University',
            'passing_year' => 2012,
        ]));

    $linked->assertCreated()
        ->assertJsonPath('data.person_id', $existing->id)
        ->assertJsonPath('data.full_name', 'Naveed Ahmed')
        ->assertJsonPath('data.qualification', 'B.Sc Agri')
        ->assertJsonPath('data.verification_status', 'pending')
        ->assertJsonPath('data.source', 'office');

    expect(Person::query()->count())->toBe(1);

    $created = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'role' => 'ceo',
            'cnic' => '5440011111112',
            'full_name' => 'Haji Abdul Maliq',
            'mobile' => '03001230002',
        ]));

    $created->assertCreated()
        ->assertJsonPath('data.role', 'ceo')
        ->assertJsonPath('data.qualification', null);

    expect(Person::query()->where('cnic', '5440011111112')->exists())->toBeTrue();
});

it('blocks active technical staff and an active dealer owner (R-01, R-03)', function () {
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create(['name' => 'Green Agro']);
    $other = Company::factory()->create(['name' => 'A.M.B. Agro Division']);
    $person = Person::factory()->create(['cnic' => '5440011111111', 'full_name' => 'M Ashraf']);
    CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'start_date' => '2024-03-01',
        'verification_status' => 'pending',
    ]);

    $blocked = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody());

    $blocked->assertUnprocessable()
        ->assertJsonPath('errors.cnic.0', 'This person is active technical staff at A.M.B. Agro Division since 2024-03-01.');

    $owner = Person::factory()->create(['cnic' => '5440011111113', 'full_name' => 'Shop Owner']);
    $dealership = DealerOwner::factory()->create(['person_id' => $owner->id]);

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people/check", [
            'role' => 'technical_staff',
            'cnic' => '54400-1111111-3',
        ])
        ->assertOk()
        ->assertJsonPath('data.found', true)
        ->assertJsonPath(
            'data.blocks.0.message',
            'This person is an active owner of '.$dealership->dealer->shop_name.' and cannot be added as technical staff.',
        );

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'cnic' => '5440011111113',
            'full_name' => 'Shop Owner',
            'mobile' => '03001230003',
        ]))
        ->assertUnprocessable();
});

it('enforces employment dates and warns on a shared mobile (R-07, R-08)', function () {
    $officer = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    Person::factory()->create(['full_name' => 'M Ashraf', 'mobile' => '03003892412']);

    $tooFar = Carbon::today()->addDays(31)->toDateString();

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'cnic' => '5440011111114',
            'start_date' => $tooFar,
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.start_date.0', 'The start date cannot be more than 30 days in the future.');

    $warning = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'cnic' => '5440011111114',
            'mobile' => '03003892412',
        ]));

    $warning->assertStatus(409)
        ->assertJsonPath('warnings.0', 'This mobile number already belongs to M Ashraf.');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody([
            'cnic' => '5440011111114',
            'mobile' => '03003892412',
            'confirm_warnings' => true,
            'warning_reason' => 'Same household',
        ]))
        ->assertCreated();

    expect(ActivityLog::query()->where('action', 'warning_overridden')->where('subject_type', 'person')->exists())->toBeTrue();
});

it('ends employment and then allows the person at another company', function () {
    $officer = companyActor('Registration Officer');
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $created = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$first->id}/people", staffBody())
        ->assertCreated();

    $assignmentId = $created->json('data.id');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/company-people/{$assignmentId}/end", [
            'end_date' => Carbon::today()->subDay()->toDateString(),
            'end_reason' => 'Resigned',
        ])
        ->assertUnprocessable();

    $ended = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/company-people/{$assignmentId}/end", [
            'end_date' => Carbon::today()->toDateString(),
            'end_reason' => 'Resigned',
        ]);

    $ended->assertOk()
        ->assertJsonPath('data.end_reason', 'Resigned');

    expect($ended->json('data.staff_note'))->toContain('minimum 2');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$second->id}/people", staffBody())
        ->assertCreated()
        ->assertJsonPath('data.person_id', $created->json('data.person_id'));
});

it('lets an officer verify technical staff and keeps company submissions pending', function () {
    $entry = companyActor('Data Entry Operator');
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create();
    $owned = Company::factory()->create();
    $companyUser = companyActor('Company Admin', [
        'user_type' => 'company',
        'company_id' => $owned->id,
    ]);

    $created = $this->actingAs($entry, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody())
        ->assertCreated();

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/company-people/'.$created->json('data.id').'/verify')
        ->assertForbidden();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/company-people/'.$created->json('data.id').'/verify')
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'verified');

    $this->actingAs($companyUser, 'sanctum')
        ->getJson("/api/v1/companies/{$company->id}/people")
        ->assertNotFound();

    $portal = $this->actingAs($companyUser, 'sanctum')
        ->postJson("/api/v1/companies/{$owned->id}/people", staffBody([
            'cnic' => '5440011111115',
            'mobile' => '03001230015',
        ]));

    $portal->assertCreated()
        ->assertJsonPath('data.source', 'portal')
        ->assertJsonPath('data.verification_status', 'pending');

    $this->actingAs($companyUser, 'sanctum')
        ->postJson('/api/v1/company-people/'.$portal->json('data.id').'/verify')
        ->assertForbidden();

    $profile = $this->actingAs($officer, 'sanctum')
        ->getJson("/api/v1/companies/{$company->id}/profile");

    $profile->assertOk()
        ->assertJsonPath('data.verified_technical_staff', 1)
        ->assertJsonPath('data.alerts.0.kind', 'staff');
});

it('hides the other company name from a company user', function () {
    $owned = Company::factory()->create();
    $other = Company::factory()->create(['name' => 'Green Agro Pvt Ltd']);
    $companyUser = companyActor('Company Admin', [
        'user_type' => 'company',
        'company_id' => $owned->id,
    ]);
    $person = Person::factory()->create(['cnic' => '5440011111116']);
    CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'start_date' => '2024-03-01',
    ]);

    $this->actingAs($companyUser, 'sanctum')
        ->postJson("/api/v1/companies/{$owned->id}/people/check", [
            'role' => 'technical_staff',
            'cnic' => '5440011111116',
        ])
        ->assertOk()
        ->assertJsonPath('data.blocks.0.message', 'This person is registered with another company. Employment must be ended there first.')
        ->assertDontSee('Green Agro');
});

it('keeps brand names unique within a company (R-14)', function () {
    $officer = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $product = Product::factory()->create([
        'generic_name' => 'Flumioxazin',
        'market_name' => 'Fiumax Active',
        'concentration' => '60%',
        'formulation' => 'WP',
    ]);
    $body = [
        'brand_name' => 'Fiumax',
        'product_id' => $product->id,
        'source' => 'own_import',
        'sample_provided' => false,
    ];

    $created = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/products", $body);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.generic_name', 'Flumioxazin')
        ->assertJsonPath('data.market_name', 'Fiumax Active')
        ->assertJsonPath('data.display_name', 'Fiumax Active (Flumioxazin)')
        ->assertJsonPath('data.product_label', 'Fiumax Active (Flumioxazin) 60% WP');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/products", array_merge($body, [
            'brand_name' => 'fiumax',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.brand_name.0', 'This brand name already exists for this company.');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$other->id}/products", $body)
        ->assertCreated();

    $this->actingAs($officer, 'sanctum')
        ->deleteJson('/api/v1/company-products/'.$created->json('data.id'), [
            'reason' => 'Entered on the wrong company',
        ])
        ->assertOk();

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/products", $body)
        ->assertUnprocessable();

    expect(ActivityLog::query()->where('action', 'deleted')->where('subject_type', 'company_product')->exists())->toBeTrue();
});

it('stores premises and assets and lists company activity', function () {
    $officer = companyActor('Registration Officer');
    $auditor = companyActor('Auditor');
    $district = companyActor('District Officer');
    $company = Company::factory()->create(['name' => 'Browser Agro']);
    $place = District::factory()->create(['name' => 'Quetta']);

    $this->actingAs($district, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody())
        ->assertForbidden();

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/premises", [
            'type' => 'warehouse',
            'district_id' => $place->id,
            'address' => 'Kasi Plaza, Quetta',
            'gps_lat' => 30.19,
            'gps_lng' => 67.00,
            'phone' => '0810000000',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.district_name', 'Quetta');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/assets", [
            'asset_type' => 'immovable',
            'description' => 'Warehouse building',
            'district_id' => $place->id,
            'estimated_value' => 1500000,
        ])
        ->assertCreated();

    $this->actingAs($auditor, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/assets", [
            'asset_type' => 'movable',
            'description' => 'Van',
        ])
        ->assertForbidden();

    License::query()->create([
        'license_no' => 'DPP/C/2026/0008',
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'license_kind' => 'registration',
        'valid_from' => Carbon::today()->subMonth()->toDateString(),
        'valid_to' => Carbon::today()->addMonths(11)->toDateString(),
        'status' => 'active',
        'verification_token' => str_repeat('c', 40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);

    $profile = $this->actingAs($auditor, 'sanctum')
        ->getJson("/api/v1/companies/{$company->id}/profile");

    $profile->assertOk()
        ->assertJsonPath('data.license.license_no', 'DPP/C/2026/0008')
        ->assertJsonPath('data.license.documents_status', 'incomplete');

    expect(collect($profile->json('data.alerts'))->pluck('kind'))->toContain('documents');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/companies/{$company->id}/people", staffBody())
        ->assertCreated();

    $activity = $this->actingAs($auditor, 'sanctum')
        ->getJson("/api/v1/companies/{$company->id}/activity");

    $activity->assertOk();
    expect(collect($activity->json('data'))->pluck('subject_type'))->toContain('company_person');
});

it('does not count a CNIC-pending person as verified staff', function () {
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create();
    $person = Person::factory()->create(['cnic_pending' => true, 'cnic' => '5440011111117']);
    $assignment = CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'verification_status' => 'verified',
        'verified_by' => $officer->id,
        'verified_at' => now(),
    ]);

    $this->actingAs($officer, 'sanctum')
        ->getJson("/api/v1/companies/{$company->id}/profile")
        ->assertOk()
        ->assertJsonPath('data.verified_technical_staff', 0);

    expect($assignment->verification_status)->toBe('verified');
});
