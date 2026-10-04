<?php

use App\Exports\DealersExport;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\District;
use App\Models\DocumentType;
use App\Models\Person;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function dealerBody(int $districtId, array $overrides = []): array
{
    if (! array_key_exists('company_ids', $overrides)) {
        $overrides['company_ids'] = [Company::factory()->create()->id];
    }

    return array_merge([
        'shop_name' => 'Kisan Zarai Markaz',
        'district_id' => $districtId,
        'tehsil_id' => null,
        'business_address' => 'Main Bazaar',
        'mobile' => '03001234567',
        'email' => null,
    ], $overrides);
}

function dealerPdf(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

    return new UploadedFile($path, 'shop.pdf', null, null, true);
}

it('creates a dealer code for the district and keeps a district officer inside assigned districts', function () {
    $entry = companyActor('Data Entry Operator');
    $officer = companyActor('District Officer');
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $pishin = District::factory()->create(['name' => 'Pishin', 'code' => 'PSH']);
    $officer->districts()->attach($quetta->id);

    $created = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($quetta->id));

    $created->assertCreated()
        ->assertJsonPath('data.dealer_code', 'D-QTA-0001')
        ->assertJsonPath('data.status', 'unlicensed');

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($pishin->id, [
            'shop_name' => 'Pishin Shop',
            'business_address' => 'Pishin Road',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.dealer_code', 'D-PSH-0001');

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/dealers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dealer_code', 'D-QTA-0001');

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/dealers/'.$created->json('data.id'))
        ->assertOk();

    $hidden = $this->actingAs($entry, 'sanctum')
        ->getJson('/api/v1/dealers?filter[district_id]='.$pishin->id);

    $hiddenId = collect($hidden->json('data'))->first()['id'];

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/dealers/'.$hiddenId)
        ->assertNotFound();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($pishin->id, [
            'shop_name' => 'Outside Shop',
            'business_address' => 'Other Road',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.district_id.0', 'Choose a district assigned to you.');
});

it('warns on the same shop name or address in the same district (R-13)', function () {
    $entry = companyActor('Data Entry Operator');
    $quetta = District::factory()->create(['code' => 'QTA']);
    $pishin = District::factory()->create(['code' => 'PSH']);

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($quetta->id))
        ->assertCreated();

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($pishin->id))
        ->assertCreated();

    $name = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($quetta->id, [
            'business_address' => 'A different street',
        ]));

    $name->assertStatus(409)
        ->assertJsonPath('warnings.0', 'A shop named "Kisan Zarai Markaz" (D-QTA-0001) already exists in this district.');

    $address = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($quetta->id, [
            'shop_name' => 'Another Markaz',
            'business_address' => 'main  bazaar',
        ]));

    $address->assertStatus(409);
    expect($address->json('warnings.0'))->toContain('This address is already used by "Kisan Zarai Markaz"');

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($quetta->id, [
            'shop_name' => 'Another Markaz',
            'business_address' => 'Main Bazaar',
            'confirm_warnings' => true,
            'warning_reason' => 'Second counter',
        ]))
        ->assertCreated();

    expect(ActivityLog::query()->where('action', 'warning_overridden')->where('subject_type', 'dealer')->exists())->toBeTrue();
});

it('blocks active technical staff as an owner and allows the same person to own more than one shop (R-03, R-04)', function () {
    $entry = companyActor('Data Entry Operator');
    $district = District::factory()->create(['code' => 'QTA']);
    $person = Person::factory()->create(['full_name' => 'Ali Khan', 'cnic' => '5440011122233']);
    $staff = CompanyPerson::factory()->create([
        'person_id' => $person->id,
        'role' => 'technical_staff',
        'verification_status' => 'verified',
        'end_date' => null,
    ]);

    $first = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id))
        ->assertCreated();
    $second = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id, [
            'shop_name' => 'Northern Markaz',
            'business_address' => 'Station Road',
            'confirm_warnings' => true,
            'warning_reason' => 'Different shop',
        ]))
        ->assertCreated();

    $blocked = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers/'.$first->json('data.id').'/owners', [
            'cnic' => '54400-1112223-3',
            'start_date' => now()->toDateString(),
        ]);

    $blocked->assertUnprocessable();
    expect($blocked->json('errors.cnic.0'))->toContain('cannot be added as an active dealer owner');

    $staff->end_date = now()->toDateString();
    $staff->end_reason = 'Resigned';
    $staff->save();

    $owner = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers/'.$first->json('data.id').'/owners', [
            'cnic' => '5440011122233',
            'start_date' => now()->toDateString(),
        ]);

    $owner->assertCreated()->assertJsonPath('data.full_name', 'Ali Khan');

    $again = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers/'.$second->json('data.id').'/owners', [
            'cnic' => '5440011122233',
            'start_date' => now()->toDateString(),
        ]);

    $again->assertCreated();
    expect($again->json('data.other_shops'))->toContain('Kisan Zarai Markaz');

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealer-owners/'.$owner->json('data.id').'/end', [
            'end_date' => now()->subDay()->toDateString(),
        ])
        ->assertUnprocessable();

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealer-owners/'.$owner->json('data.id').'/end', [
            'end_date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.end_date', now()->toDateString());
});

it('exports the filtered list and soft-deletes with a reason', function () {
    Excel::fake();
    $admin = companyActor('Super Admin');
    $auditor = companyActor('Auditor');
    $entry = companyActor('Data Entry Operator');
    $district = District::factory()->create(['name' => 'Awaran', 'code' => 'AWR']);

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id, ['shop_name' => 'Hamal Saba']))
        ->assertCreated();

    $this->actingAs($entry, 'sanctum')->get('/api/v1/exports/dealers')->assertForbidden();
    $this->actingAs($auditor, 'sanctum')
        ->putJson('/api/v1/dealers/'.$created->json('data.id'), dealerBody($district->id, ['shop_name' => 'Changed']))
        ->assertForbidden();

    $this->actingAs($auditor, 'sanctum')->get('/api/v1/exports/dealers?search=Hamal');

    Excel::assertDownloaded('dealers.xlsx', function (DealersExport $export) {
        return $export->collection()->contains(fn (array $row) => $row[1] === 'Hamal Saba' && $row[2] === 'Awaran');
    });

    $this->actingAs($auditor, 'sanctum')
        ->deleteJson('/api/v1/dealers/'.$created->json('data.id'), ['reason' => 'Entered twice'])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/dealers/'.$created->json('data.id'), ['reason' => 'Entered twice'])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dealers')
        ->assertJsonCount(0, 'data');

    expect(ActivityLog::query()->where('action', 'deleted')->where('subject_type', 'dealer')->exists())->toBeTrue();
});

it('stores a dealer document and warns when the same file is already on a company', function () {
    Storage::fake('local');
    $entry = companyActor('Data Entry Operator');
    $companyUser = companyActor('Company Admin', ['user_type' => 'company']);
    $district = District::factory()->create(['code' => 'QTA']);
    $type = DocumentType::factory()->create(['applies_to' => 'any', 'name' => 'Agreement']);
    $companyOnly = DocumentType::factory()->create(['applies_to' => 'company', 'name' => 'Incorporation']);
    $dealer = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id))
        ->assertCreated();

    $company = Company::factory()->create(['name' => 'Green Agro']);
    $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => dealerPdf(),
            'document_type_id' => $type->id,
            'title' => 'Shared scan',
            'attested_by' => 'none',
        ])
        ->assertCreated();

    $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/dealers/'.$dealer->json('data.id').'/documents', [
            'file' => dealerPdf(),
            'document_type_id' => $companyOnly->id,
            'title' => 'Wrong type',
            'attested_by' => 'none',
        ])
        ->assertUnprocessable();

    $warning = $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/dealers/'.$dealer->json('data.id').'/documents', [
            'file' => dealerPdf(),
            'document_type_id' => $type->id,
            'title' => 'Shop agreement',
            'attested_by' => 'none',
        ]);

    $warning->assertStatus(409)
        ->assertJsonPath('warnings.0', 'This file is already attached to Green Agro.');

    $this->actingAs($companyUser, 'sanctum')
        ->getJson('/api/v1/dealers')
        ->assertForbidden();

    $this->actingAs($companyUser, 'sanctum')
        ->getJson('/api/v1/dealers/'.$dealer->json('data.id').'/documents')
        ->assertNotFound();
});

it('requires at least one company and hides soft-deleted companies from the link', function () {
    $entry = companyActor('Data Entry Operator');
    $district = District::factory()->create(['code' => 'QTA']);
    $first = Company::factory()->create(['name' => 'Alpha Agro']);
    $second = Company::factory()->create(['name' => 'Beta Agro']);
    $gone = Company::factory()->create(['name' => 'Gone Agro']);
    $gone->delete();

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id, [
            'company_ids' => [],
        ]))
        ->assertUnprocessable();

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id, [
            'company_ids' => [$gone->id],
        ]))
        ->assertUnprocessable();

    $created = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/dealers', dealerBody($district->id, [
            'company_ids' => [$first->id, $second->id],
        ]));

    $created->assertCreated()
        ->assertJsonPath('data.company_ids', [$first->id, $second->id])
        ->assertJsonCount(2, 'data.companies');

    $this->actingAs($entry, 'sanctum')
        ->getJson('/api/v1/dealers/'.$created->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.dealer.companies.0.name', 'Alpha Agro');

    $profile = $this->actingAs($entry, 'sanctum')
        ->getJson('/api/v1/companies/'.$first->id.'/profile');

    $profile->assertOk()
        ->assertJsonPath('data.dealers.0.shop_name', 'Kisan Zarai Markaz')
        ->assertJsonPath('data.dealers.0.dealer_code', 'D-QTA-0001');

    $choices = $this->actingAs($entry, 'sanctum')->getJson('/api/v1/dealers');
    $choices->assertOk();
    expect(collect($choices->json('meta.companies'))->pluck('id'))->toContain($first->id)
        ->and(collect($choices->json('meta.companies'))->pluck('id'))->not->toContain($gone->id);

    $this->actingAs($entry, 'sanctum')
        ->putJson('/api/v1/dealers/'.$created->json('data.id'), dealerBody($district->id, [
            'company_ids' => [$second->id],
        ]))
        ->assertOk()
        ->assertJsonPath('data.company_ids', [$second->id]);

    expect($first->fresh()->dealers)->toHaveCount(0)
        ->and($second->fresh()->dealers)->toHaveCount(1);
});
