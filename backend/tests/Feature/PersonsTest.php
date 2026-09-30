<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\Person;
use App\Models\Qualification;
use App\Models\User;
use App\Services\Persons\PersonRules;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function personUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole($role);

    return $user;
}

function personPayload(Person $person, array $overrides = []): array
{
    return array_merge([
        'full_name' => $person->full_name,
        'father_name' => $person->father_name,
        'gender' => $person->gender,
        'date_of_birth' => $person->date_of_birth?->toDateString(),
        'mobile' => $person->mobile,
        'alt_mobile' => $person->alt_mobile,
        'email' => $person->email,
        'address' => $person->address,
        'cnic' => $person->cnic,
        'cnic_pending' => $person->cnic_pending,
    ], $overrides);
}

it('blocks a CNIC that is not 13 unique digits (R-05)', function () {
    $officer = personUser('Data Entry Operator');
    $person = Person::factory()->create(['cnic' => '5440011111111', 'full_name' => 'M Ashraf']);
    $other = Person::factory()->create(['cnic' => '5440022222222']);

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=54400')
        ->assertUnprocessable()
        ->assertJsonPath('errors.cnic.0', 'CNIC must be exactly 13 digits.');

    $found = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=54400-1111111-1');

    $found->assertOk()
        ->assertJsonPath('data.found', true)
        ->assertJsonPath('data.person.cnic', '5440011111111')
        ->assertJsonPath('data.person.cnic_display', '54400-1111111-1');

    $this->actingAs($officer, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'cnic' => '544001111111',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.cnic.0', 'CNIC must be exactly 13 digits.');

    $this->actingAs($officer, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'cnic' => $other->cnic,
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.cnic.0', 'This CNIC already belongs to another person.');
});

it('returns the existing person and does not create a duplicate (R-06)', function () {
    $officer = personUser('Registration Officer');
    $person = Person::factory()->create(['cnic' => '5440033333333']);
    $before = Person::query()->count();

    $lookup = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440033333333');

    $lookup->assertOk()
        ->assertJsonPath('data.found', true)
        ->assertJsonPath('data.person.id', $person->id);

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440044444444')
        ->assertOk()
        ->assertJsonPath('data.found', false)
        ->assertJsonPath('data.person', null);

    expect(Person::query()->count())->toBe($before);
});

it('warns when a mobile number belongs to someone else and logs the reason (R-07)', function () {
    $officer = personUser('Data Entry Operator');
    $person = Person::factory()->create([
        'full_name' => 'First Person',
        'mobile' => '03001110001',
    ]);
    Person::factory()->create([
        'full_name' => 'Second Person',
        'mobile' => '03001110002',
        'alt_mobile' => '03001110003',
    ]);

    $warned = $this->actingAs($officer, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'mobile' => '03001110002',
        ]));

    $warned->assertStatus(409)
        ->assertJsonPath('warnings.0', 'This mobile number already belongs to Second Person.');
    expect($person->fresh()->mobile)->toBe('03001110001');

    $this->actingAs($officer, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'mobile' => '03001110002',
            'confirm_warnings' => true,
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.warning_reason.0', 'A reason is required to confirm this warning.');

    $saved = $this->actingAs($officer, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'alt_mobile' => '03001110003',
            'confirm_warnings' => true,
            'warning_reason' => 'Same household phone',
        ]));

    $saved->assertOk()->assertJsonPath('data.alt_mobile', '03001110003');

    $log = ActivityLog::query()
        ->where('subject_type', 'person')
        ->where('subject_id', $person->id)
        ->where('action', 'warning_overridden')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['warning_reason'])->toBe('Same household phone')
        ->and($log->description)->toContain('Same household phone');
});

it('names the other company and start date and keeps one active technical post (R-01, R-02)', function () {
    $officer = personUser('Director');
    $person = Person::factory()->create(['cnic' => '5440055555555']);
    $company = Company::factory()->create(['name' => 'A.M.B. Agro Division']);
    $other = Company::factory()->create(['name' => 'Lala Agro']);

    CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'start_date' => '2021-04-01',
        'end_date' => null,
        'verification_status' => 'verified',
    ]);

    $lookup = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440055555555');

    $lookup->assertOk();
    $blocks = collect($lookup->json('data.person.blocks'));

    expect($blocks->firstWhere('rule', 'R-01')['message'])
        ->toBe('This person is active technical staff at A.M.B. Agro Division since 2021-04-01.')
        ->and($blocks->firstWhere('rule', 'R-02')['message'])
        ->toBe('Enter the end date at A.M.B. Agro Division before moving this person to another company.');

    expect(fn () => CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'start_date' => '2024-01-01',
        'end_date' => null,
        'verification_status' => 'pending',
    ]))->toThrow(UniqueConstraintViolationException::class);

    CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'start_date' => '2020-01-01',
        'end_date' => null,
        'verification_status' => 'rejected',
        'rejection_reason' => 'Documents missing',
    ]);

    $rejected = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/'.$person->id);

    expect(collect($rejected->json('data.blocks'))->where('rule', 'R-01'))->toHaveCount(1);
});

it('blocks an active technical staff member and an active dealer owner from holding both roles (R-03)', function () {
    $officer = personUser('Registration Officer');
    $staff = Person::factory()->create(['cnic' => '5440066666661']);
    $owner = Person::factory()->create(['cnic' => '5440066666662']);
    $company = Company::factory()->create(['name' => 'A.M.B. Agro Division']);
    $dealer = Dealer::factory()->create(['shop_name' => 'Hamal Saba Zarai Markaz']);

    CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => $staff->id,
        'role' => 'technical_staff',
        'start_date' => '2021-04-01',
    ]);
    DealerOwner::factory()->create([
        'dealer_id' => $dealer->id,
        'person_id' => $owner->id,
        'start_date' => '2020-01-15',
    ]);

    $staffLookup = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440066666661');
    $ownerLookup = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440066666662');

    expect($staffLookup->json('data.person.blocks'))->toContain([
        'rule' => 'R-03',
        'for' => 'dealer_owner',
        'message' => 'This person is active technical staff at A.M.B. Agro Division and cannot be added as an active dealer owner.',
    ])->and($ownerLookup->json('data.person.blocks'))->toContain([
        'rule' => 'R-03',
        'for' => 'technical_staff',
        'message' => 'This person is an active owner of Hamal Saba Zarai Markaz and cannot be added as technical staff.',
    ]);
});

it('lists every current shop and does not block another shop (R-04)', function () {
    $officer = personUser('Super Admin');
    $person = Person::factory()->create(['cnic' => '5440077777777']);
    $first = Dealer::factory()->create(['shop_name' => 'Hamal Saba Zarai Markaz']);
    $second = Dealer::factory()->create(['shop_name' => 'Quetta Agri Store']);

    DealerOwner::factory()->create([
        'dealer_id' => $first->id,
        'person_id' => $person->id,
        'start_date' => '2019-03-01',
    ]);
    DealerOwner::factory()->create([
        'dealer_id' => $second->id,
        'person_id' => $person->id,
        'start_date' => '2022-08-01',
        'end_date' => '2023-01-01',
    ]);
    DealerOwner::factory()->create([
        'dealer_id' => Dealer::factory()->create(['shop_name' => 'Sibi Seed House'])->id,
        'person_id' => $person->id,
        'start_date' => '2024-02-01',
    ]);

    $lookup = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440077777777');

    $roles = collect($lookup->json('data.person.roles'))->where('kind', 'dealer_owner');
    $blocks = collect($lookup->json('data.person.blocks'));

    expect($roles)->toHaveCount(3)
        ->and($roles->where('current', true)->pluck('organisation')->all())
        ->toEqualCanonicalizing(['Hamal Saba Zarai Markaz', 'Sibi Seed House'])
        ->and($blocks->where('for', 'dealer_owner'))->toBeEmpty()
        ->and($blocks->where('rule', 'R-03'))->toHaveCount(2);
});

it('refuses an end date before the start date and a start date more than 30 days ahead (R-08)', function () {
    $rules = app(PersonRules::class);
    $tooFar = Carbon::today()->addDays(31)->toDateString();
    $allowed = Carbon::today()->addDays(30)->toDateString();

    expect($rules->employmentDateErrors('2026-01-01', '2025-12-31'))
        ->toContain('The end date cannot be before the start date.')
        ->and($rules->employmentDateErrors($tooFar, null))
        ->toContain('The start date cannot be more than 30 days in the future.')
        ->and($rules->employmentDateErrors($allowed, null))->toBeEmpty()
        ->and($rules->employmentDateErrors('2026-01-01', '2026-01-01'))->toBeEmpty()
        ->and($rules->employmentDateErrors('2026-01-01', null))->toBeEmpty();
});

it('does not count a pending CNIC as verified staff and blocks a license for current links (R-09)', function () {
    $rules = app(PersonRules::class);
    $pending = Person::factory()->create([
        'cnic' => null,
        'cnic_pending' => true,
        'full_name' => 'Legacy Person',
    ]);
    $company = Company::factory()->create();
    $staff = CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => $pending->id,
        'role' => 'technical_staff',
        'verification_status' => 'verified',
        'end_date' => null,
    ]);

    expect($rules->countsAsVerifiedStaff($pending, $staff))->toBeFalse()
        ->and($rules->blocksLicense($pending))->toBeTrue();

    $staff->update(['end_date' => '2024-06-01']);

    expect($rules->blocksLicense($pending->fresh()))->toBeFalse();

    CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => $pending->id,
        'role' => 'contact_person',
        'verification_status' => 'verified',
        'end_date' => null,
    ]);

    expect($rules->blocksLicense($pending->fresh()))->toBeFalse();

    CompanyPerson::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'person_id' => $pending->id,
        'role' => 'ceo',
        'verification_status' => 'pending',
        'end_date' => null,
    ]);

    expect($rules->blocksLicense($pending->fresh()))->toBeTrue();

    $owner = Person::factory()->create([
        'cnic' => null,
        'cnic_pending' => true,
    ]);
    $dealerOwner = DealerOwner::factory()->create([
        'person_id' => $owner->id,
        'end_date' => null,
    ]);

    expect($rules->blocksLicense($owner))->toBeTrue();

    $dealerOwner->delete();

    expect($rules->blocksLicense($owner->fresh()))->toBeFalse();

    $verified = Person::factory()->create(['cnic_pending' => false]);
    $verifiedStaff = CompanyPerson::factory()->create([
        'person_id' => $verified->id,
        'role' => 'technical_staff',
        'verification_status' => 'verified',
        'end_date' => null,
    ]);

    expect($rules->countsAsVerifiedStaff($verified, $verifiedStaff))->toBeTrue()
        ->and($rules->blocksLicense($verified))->toBeFalse();
});

it('saves optional qualifications and hides persons from company users and district officers', function () {
    $entry = personUser('Data Entry Operator');
    $auditor = personUser('Auditor');
    $officer = personUser('District Officer');
    $companyAdmin = User::factory()->create([
        'must_change_password' => false,
        'user_type' => 'company',
    ]);
    $companyAdmin->assignRole('Company Admin');

    $person = Person::factory()->create([
        'full_name' => 'M Ashraf',
        'cnic' => '5440088888888',
        'mobile' => '03003892412',
    ]);
    $qualification = Qualification::factory()->create(['name' => 'B.Sc. Agriculture']);
    $existing = $person->qualifications()->create([
        'qualification_id' => $qualification->id,
        'institution' => 'Old College',
        'passing_year' => 2010,
    ]);

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/persons/lookup?cnic=5440088888888')
        ->assertForbidden();
    $this->actingAs($companyAdmin, 'sanctum')
        ->getJson('/api/v1/persons/'.$person->id)
        ->assertForbidden();
    $this->actingAs($auditor, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'father_name' => 'Blocked',
        ]))
        ->assertForbidden();

    $saved = $this->actingAs($entry, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person, [
            'father_name' => 'Ahmed',
            'gender' => 'male',
            'qualifications' => [
                [
                    'id' => $existing->id,
                    'qualification_id' => $qualification->id,
                    'institution' => 'Agriculture University',
                    'passing_year' => 2012,
                ],
            ],
        ]));

    $saved->assertOk()
        ->assertJsonPath('data.father_name', 'Ahmed')
        ->assertJsonPath('data.qualifications.0.institution', 'Agriculture University')
        ->assertJsonPath('data.qualifications.0.passing_year', 2012);

    expect($person->fresh()->normalized_name)->toBe('m ashraf');
    expect(ActivityLog::query()->where('subject_type', 'person')->where('action', 'updated')->count())->toBe(1);

    $this->actingAs($entry, 'sanctum')
        ->putJson('/api/v1/persons/'.$person->id, personPayload($person->fresh(), [
            'qualifications' => [],
        ]))
        ->assertOk()
        ->assertJsonPath('data.qualifications', []);

    expect($person->qualifications()->count())->toBe(0);

    $profile = $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/persons/'.$person->id);

    $profile->assertOk()
        ->assertJsonPath('data.qualification_choices', [])
        ->assertJsonPath('data.roles', []);
});
