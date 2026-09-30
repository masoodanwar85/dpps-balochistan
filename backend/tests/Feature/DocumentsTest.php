<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Setting;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

function documentFile(string $contents, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, null, null, true);
}

function samplePdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

it('rejects a file whose contents are not a PDF, JPG, or PNG and stores a real PDF', function () {
    $officer = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Incorporation', 'category' => 'other', 'applies_to' => 'company']);

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile('not a pdf', 'fake.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Fake',
            'attested_by' => 'none',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'This file type is not allowed. Upload a PDF, JPG, or PNG.');

    $created = $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile(samplePdf(), 'incorporation.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Certificate of Incorporation',
            'attested_by' => 'none',
        ]);

    $created->assertCreated()
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.verification_status', 'pending')
        ->assertJsonPath('data.uploaded_via', 'office')
        ->assertJsonPath('data.version_no', 1);

    Storage::disk('local')->assertExists(Document::query()->find($created->json('data.id'))->file_path);
});

it('warns an officer when the same file is already attached to another company (R-15)', function () {
    $officer = companyActor('Registration Officer');
    $first = Company::factory()->create(['name' => 'Green Agro']);
    $second = Company::factory()->create(['name' => 'Northern Agro']);
    $type = DocumentType::factory()->create(['applies_to' => 'any']);
    $pdf = samplePdf();
    $fields = [
        'document_type_id' => $type->id,
        'title' => 'Degree',
        'attested_by' => 'gazetted_officer',
    ];

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$first->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree.pdf'),
        ]))
        ->assertCreated();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$first->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree-again.pdf'),
            'title' => 'Degree copy',
        ]))
        ->assertCreated();

    $warning = $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$second->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree.pdf'),
        ]));

    $warning->assertStatus(409)
        ->assertJsonPath('warnings.0', 'This file is already attached to Green Agro.');

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$second->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree.pdf'),
            'confirm_warnings' => '1',
            'warning_reason' => 'Same scanned degree',
        ]))
        ->assertCreated();

    Document::query()->where('documentable_id', $first->id)->delete();

    $third = Company::factory()->create();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$third->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree.pdf'),
        ]))
        ->assertStatus(409)
        ->assertJsonPath('warnings.0', 'This file is already attached to Northern Agro.');

    Document::query()->where('documentable_id', $second->id)->delete();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$third->id.'/documents', array_merge($fields, [
            'file' => documentFile($pdf, 'degree.pdf'),
        ]))
        ->assertCreated();

    expect(ActivityLog::query()->where('action', 'warning_overridden')->where('subject_type', 'document')->exists())->toBeTrue();
});

it('does not show the duplicate-file warning to a company user', function () {
    $owned = Company::factory()->create();
    $other = Company::factory()->create(['name' => 'Hidden Agro']);
    $officer = companyActor('Data Entry Operator');
    $companyUser = companyActor('Company Admin', [
        'user_type' => 'company',
        'company_id' => $owned->id,
    ]);
    $type = DocumentType::factory()->create(['applies_to' => 'company']);
    $pdf = samplePdf();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$other->id.'/documents', [
            'file' => documentFile($pdf, 'shared.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Shared',
            'attested_by' => 'none',
        ])
        ->assertCreated();

    $this->actingAs($companyUser, 'sanctum')
        ->post('/api/v1/companies/'.$owned->id.'/documents', [
            'file' => documentFile($pdf, 'shared.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Our copy',
            'attested_by' => 'none',
        ])
        ->assertCreated()
        ->assertJsonPath('data.uploaded_via', 'portal')
        ->assertJsonPath('data.verification_status', 'pending');

    $this->actingAs($companyUser, 'sanctum')
        ->getJson('/api/v1/companies/'.$other->id.'/documents')
        ->assertNotFound();
});

it('keeps the old version when a document is replaced and verifies only the current one', function () {
    $entry = companyActor('Data Entry Operator');
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create();
    $type = DocumentType::factory()->create(['category' => 'agreement', 'applies_to' => 'any']);

    $created = $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile(samplePdf(), 'agreement-v1.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Purchase agreement',
            'attested_by' => 'none',
            'expiry_date' => '2027-01-01',
        ])
        ->assertCreated();

    $id = $created->json('data.id');

    $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/documents/'.$id.'/verify')
        ->assertForbidden();

    $replaced = $this->actingAs($entry, 'sanctum')
        ->post('/api/v1/documents/'.$id.'/replace', [
            'file' => documentFile("%PDF-1.4\n2 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 2 0 R>>\n%%EOF\n", 'agreement-v2.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Purchase agreement',
            'attested_by' => 'notary_public',
        ]);

    $replaced->assertCreated()
        ->assertJsonPath('data.version_no', 2)
        ->assertJsonPath('data.verification_status', 'pending');

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/documents/'.$id.'/verify')
        ->assertUnprocessable();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/documents/'.$replaced->json('data.id').'/verify')
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'verified');

    $list = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/companies/'.$company->id.'/documents?category=agreement');

    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.version_no', 2);

    $this->actingAs($entry, 'sanctum')
        ->putJson('/api/v1/documents/'.$replaced->json('data.id'), [
            'document_type_id' => $type->id,
            'title' => 'Purchase agreement revised',
            'attested_by' => 'notary_public',
            'expiry_date' => '2028-06-01',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Purchase agreement revised')
        ->assertJsonPath('data.expiry_date', '2028-06-01')
        ->assertJsonPath('data.verification_status', 'verified');

    $history = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/documents/'.$replaced->json('data.id').'/history');

    $history->assertOk()->assertJsonCount(2, 'data');
    expect($history->json('data.0.version_no'))->toBe(2)
        ->and($history->json('data.1.version_no'))->toBe(1)
        ->and(Document::query()->find($id)->replaced_by_id)->toBe($replaced->json('data.id'));
});

it('issues a five-minute download link and refuses it after it expires', function () {
    Storage::persistentFake('local', ['serve' => true]);
    $auditor = companyActor('Auditor');
    $company = Company::factory()->create();
    $type = DocumentType::factory()->create();
    $officer = companyActor('Data Entry Operator');

    $created = $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile(samplePdf(), 'view.pdf'),
            'document_type_id' => $type->id,
            'title' => 'View me',
            'attested_by' => 'none',
        ])
        ->assertCreated();

    $this->actingAs($auditor, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile(samplePdf(), 'no.pdf'),
            'document_type_id' => $type->id,
            'title' => 'No',
            'attested_by' => 'none',
        ])
        ->assertForbidden();

    $link = $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/documents/'.$created->json('data.id').'/download-url');

    $link->assertOk();
    $parts = parse_url($link->json('data.url'));
    $signed = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');

    $this->get($signed)->assertOk();

    $this->travel(6)->minutes();

    $this->get($signed)->assertForbidden();
    expect($link->json('data.url'))->toContain('signature=');
    expect(ActivityLog::query()->where('action', 'downloaded')->where('subject_type', 'document')->count())->toBe(1);
    expect(json_encode(ActivityLog::query()->where('action', 'downloaded')->first()))->not->toContain('signature=');

    Storage::disk('local')->delete(Document::query()->find($created->json('data.id'))->file_path);
});

it('refuses a file larger than the upload setting', function () {
    Setting::query()->where('key', 'max_upload_size_mb')->update(['value' => '1']);
    $officer = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    $type = DocumentType::factory()->create();

    $this->actingAs($officer, 'sanctum')
        ->post('/api/v1/companies/'.$company->id.'/documents', [
            'file' => documentFile("%PDF-1.4\n".str_repeat('0', 1024 * 1024), 'big.pdf'),
            'document_type_id' => $type->id,
            'title' => 'Too big',
            'attested_by' => 'none',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'This file is larger than the maximum of 1 MB.');
});
