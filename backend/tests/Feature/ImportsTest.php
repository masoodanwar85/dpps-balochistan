<?php

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyPremise;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\District;
use App\Models\License;
use App\Models\Person;
use App\Models\Province;
use App\Models\Tehsil;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function importUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole($role);

    return $user;
}

function importWorkbook(array $sheets): UploadedFile
{
    $book = new Spreadsheet;
    $first = true;

    foreach ($sheets as $title => $rows) {
        $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
        $first = false;
        $sheet->setTitle($title);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValue([$column + 1, $rowIndex + 1], $value);
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'dpps').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('dry-runs a company workbook and writes it after exceptions are resolved', function () {
    $admin = importUser('Super Admin');
    $entry = importUser('Data Entry Operator');
    Province::factory()->create(['name' => 'Balochistan']);
    District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);

    $file = importWorkbook([
        'Company Details' => [
            [],
            ['Visited', 'S.No', 'Reg', 'Name', 'Address', 'NTN #', 'Reg', 'PTCL', 'Phone #', 'Gmail', 'Licenses Exp', 'PCPA/Croplife', 'Field office/Ware house Bln', 'Dealers', 'chalan #', 'Amount', 'CSR', 'RD'],
            ['', '1', '905', 'Quetta Agro House', 'Jinnah Road, Quetta', '1234567', '/Renewal/PP/DGA', 'Nill', '03001234567', 'agro@example.com', '26-11-2026', 'PCPA', 'Warehouse Plaza, Quetta', '', '', '', 'Paid', 'Noted'],
            ['', '2', '58', 'Different Chemicals', 'Airport Road, Quetta', '7654321', '/PP/DGA', 'Nill', '03007654321', 'other@example.com', '--', '', '', '', '', '', '', ''],
            ['', '3', '77', 'Sadiqabad Seed Store', '25-A Industrial Estate Multan', '', '', '', '', '', '26-12-2026', '', '', '', '', '', '', ''],
        ],
        'Employ details' => [
            [],
            ['', 'S.No', 'Name Of Company', 'Name Of CEO', 'CNIC', 'TS 1', 'Contact', 'TS2', 'Contact'],
            ['', '1', 'Quetta Agro House', 'Haji Abdul', '54400-0464574-1', 'Mansoor Ahmed', '03001112222', '2', ''],
            ['', '2', 'Sadiqabad Seed Store', 'Noor Ahmed', '54400-1111111-1', 'Ali Raza', '', '', ''],
        ],
        'Name Of Products' => [
            ['S.No', 'Name of Company', 'Name of products', 'Samples#'],
            ['1', 'Quetta Agro House', "Chlorpyrifos 40% EC\nMystery Brand", 'Provided'],
        ],
    ]);

    $this->actingAs($entry, 'sanctum')->post('/api/v1/imports', [
        'file' => $file,
        'type' => 'companies',
        'mode' => 'dry_run',
    ])->assertForbidden();

    $dry = $this->actingAs($admin, 'sanctum')->post('/api/v1/imports', [
        'file' => $file,
        'type' => 'companies',
        'mode' => 'dry_run',
    ]);
    $dry->assertCreated();
    expect(Company::query()->count())->toBe(0)
        ->and($dry->json('data.committed'))->toBeFalse()
        ->and(collect($dry->json('data.exceptions'))->pluck('issue_type')->all())->toContain('bad_date', 'other');

    $batchId = $dry->json('data.id');
    $badDate = collect($dry->json('data.exceptions'))->firstWhere('issue_type', 'bad_date');
    $staff = collect($dry->json('data.exceptions'))->first(
        fn (array $row) => $row['issue_type'] === 'other' && str_contains($row['issue_details'], 'TS 2')
    );

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/import-exceptions/'.$badDate['id'], [
        'resolution' => 'skip',
    ])->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/import-exceptions/'.$staff['id'], [
        'resolution' => 'skip',
    ])->assertOk();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/imports/'.$batchId.'/run')->assertOk()
        ->assertJsonPath('data.committed', true);

    $company = Company::query()->where('name', 'Quetta Agro House')->first();
    expect($company)->not->toBeNull()
        ->and($company->legacy_reg_no)->toBe('905 /Renewal/PP/DGA')
        ->and($company->legacy_notes)->toContain('CSR: Paid')
        ->and($company->pcpa_member)->toBeTrue()
        ->and($company->landline)->toBeNull()
        ->and($company->city)->toBe('Quetta')
        ->and($company->legal_type)->toBe('other')
        ->and($company->status)->toBe('expiring');

    $license = License::query()->where('license_no', '905 /Renewal/PP/DGA')->first();
    expect($license)->not->toBeNull()
        ->and($license->is_legacy)->toBeTrue()
        ->and($license->documents_status)->toBe('not_applicable')
        ->and($license->license_kind)->toBe('renewal')
        ->and($license->valid_to->toDateString())->toBe('2026-11-26')
        ->and($license->application_id)->toBeNull();

    expect(Company::query()->where('name', 'Different Chemicals')->where('status', 'unlicensed')->exists())->toBeTrue()
        ->and(License::query()->where('licensable_id', Company::query()->where('name', 'Different Chemicals')->value('id'))->exists())->toBeFalse();

    $ceo = Person::query()->where('cnic', '5440004645741')->first();
    expect($ceo)->not->toBeNull()
        ->and($ceo->cnic_pending)->toBeFalse();
    $staffPerson = Person::query()->where('full_name', 'Mansoor Ahmed')->first();
    expect($staffPerson)->not->toBeNull()
        ->and($staffPerson->cnic_pending)->toBeTrue()
        ->and(CompanyPerson::query()->where('person_id', $staffPerson->id)->where('verification_status', 'pending')->where('source', 'import')->exists())->toBeTrue()
        ->and(Person::query()->where('full_name', '2')->exists())->toBeFalse()
        ->and(CompanyProduct::query()->where('company_id', $company->id)->where('status', 'needs_mapping')->where('sample_provided', true)->count())->toBe(2)
        ->and(CompanyPremise::query()->where('company_id', $company->id)->where('type', 'warehouse')->count())->toBe(1);

    $outside = Company::query()->where('name', 'Sadiqabad Seed Store')->first();
    expect($outside)->not->toBeNull()
        ->and($outside->city)->toBe('Not recorded')
        ->and($outside->head_office_address)->toBe('25-A Industrial Estate Multan')
        ->and($outside->legacy_notes)->toContain('Temporary city: Not recorded')
        ->and($outside->legacy_notes)->toContain('Temporary province: Balochistan')
        ->and($outside->legacy_notes)->toContain('Temporary mobile for Noor Ahmed.')
        ->and($outside->legacy_notes)->toContain('Temporary mobile for Ali Raza.')
        ->and(Person::query()->where('full_name', 'Ali Raza')->value('mobile'))->toBe('00000000000')
        ->and($outside->province_id)->toBe(Province::query()->where('name', 'Balochistan')->value('id'));
});

it('imports dealers from By District and ignores the date columns and yearly sheets', function () {
    $admin = importUser('Super Admin');
    $district = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);

    $file = importWorkbook([
        'By District' => [
            ['S# Province', 'S# District', 'District', 'Buisness Address', 'Area/Tehsil', 'Name of Onwer', 'Contact No', 'Registration No.', '', '', '', 'Renewal', 'Regisration', 'fee'],
            ['', '', '', '', '', '', '', '', 'day', 'Month', 'Year', '', '', ''],
            ['1', '1', 'Quetta', 'Hamal Saba Zarai Markaz Quetta', 'Quetta', 'Hamal Khan', '03337837791', '0001/2020', '23', '8', '23', '0', '0', '2500'],
            ['2', '1', 'Quetta', 'Second Shop Quetta', 'Quetta', 'Second Owner', '03330000002', '0001/2020', '', '', '', '', '', ''],
            ['3', '1', 'Unknownville', 'Nowhere Shop', 'Nowhere', 'Nobody', '03330000003', '0002/2020', '', '', '', '', '', ''],
        ],
        '2021-22' => [
            ['List of Dealers for the year 2021-2022'],
            ['This sheet must not create a dealer'],
        ],
    ]);

    $dry = $this->actingAs($admin, 'sanctum')->post('/api/v1/imports', [
        'file' => $file,
        'type' => 'dealers',
        'mode' => 'dry_run',
    ]);
    $dry->assertCreated();
    expect(Dealer::query()->count())->toBe(0)
        ->and(collect($dry->json('data.exceptions'))->pluck('issue_type')->all())->toContain('possible_duplicate', 'unknown_district');

    foreach ($dry->json('data.exceptions') as $exception) {
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/import-exceptions/'.$exception['id'], [
            'resolution' => 'skip',
        ])->assertOk();
    }

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/imports/'.$dry->json('data.id').'/run')->assertOk();

    $dealer = Dealer::query()->first();
    expect(Dealer::query()->count())->toBe(1)
        ->and($dealer->shop_name)->toBe('Hamal Saba Zarai Markaz')
        ->and($dealer->district_id)->toBe($district->id)
        ->and($dealer->legacy_reg_no)->toBe('0001/2020')
        ->and($dealer->legacy_notes)->toContain('fee: 2500')
        ->and($dealer->status)->toBe('unlicensed')
        ->and(License::query()->count())->toBe(0)
        ->and(Tehsil::query()->where('district_id', $district->id)->where('name', 'Quetta')->exists())->toBeTrue()
        ->and(Person::query()->where('full_name', 'Hamal Khan')->where('cnic_pending', true)->whereNull('cnic')->exists())->toBeTrue()
        ->and(Dealer::query()->where('shop_name', 'like', '%2021%')->exists())->toBeFalse();
});
