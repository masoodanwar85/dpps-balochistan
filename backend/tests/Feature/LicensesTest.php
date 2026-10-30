<?php

use App\Models\ActivityLog;
use App\Models\ApplicationChecklistItem;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\District;
use App\Models\DocumentType;
use App\Models\License;
use App\Models\Person;
use App\Models\Product;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Services\Licenses\CertificateQr;
use App\Services\Licenses\LicenseNumbers;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->seed(WorkflowStageSeeder::class);
    feeRates();
});

function licenseStaff(Company $company, int $userId, int $count = 2): void
{
    for ($index = 0; $index < $count; $index++) {
        CompanyPerson::factory()->create([
            'company_id' => $company->id,
            'person_id' => Person::factory()->create([
                'cnic_pending' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ])->id,
            'role' => 'technical_staff',
            'verification_status' => 'verified',
            'verified_by' => $userId,
            'verified_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}

function reachIssuance($case, $actor, Company $company, bool $template = true): int
{
    if ($template) {
        applicationTemplate('company', 'new');
    }
    $created = $case->actingAs($actor, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'new',
    ])->assertCreated();
    $id = $created->json('data.application.id');
    $submission = collect($created->json('data.stages'))->firstWhere('code', 'submission')['id'];
    $reviewed = $case->actingAs($actor, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$submission}/complete")->assertOk();
    $fileReview = collect($reviewed->json('data.stages'))->firstWhere('state', 'current')['id'];
    $deficient = $case->actingAs($actor, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$fileReview}/complete")->assertOk();
    $deficiency = collect($deficient->json('data.stages'))->firstWhere('state', 'current')['id'];
    $fee = $case->actingAs($actor, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$deficiency}/skip", [
        'remarks' => 'Nothing is deficient.',
    ])->assertOk();
    $feeStage = collect($fee->json('data.stages'))->firstWhere('state', 'current')['id'];
    $case->actingAs($actor, 'sanctum')->postJson("/api/v1/applications/{$id}/challans", [
        'challan_no' => 'TR-'.Str::upper(Str::random(6)),
        'bank_name' => 'National Bank',
        'payment_date' => now()->toDateString(),
        'amount' => 50000,
    ])->assertCreated();
    $challanId = $case->actingAs($actor, 'sanctum')->getJson("/api/v1/applications/{$id}")->json('data.fees.challans.0.id');
    $case->actingAs($actor, 'sanctum')->postJson("/api/v1/challans/{$challanId}/verify", [
        'verification_status' => 'verified',
    ])->assertOk();
    $case->actingAs($actor, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$feeStage}/complete")->assertOk();

    return $id;
}

it('issues a license when the checks pass and keeps documents incomplete while enforcement is off', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create();
    $id = reachIssuance($this, $director, $company);

    $this->actingAs($officer, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])->assertForbidden();

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])
        ->assertStatus(422)
        ->assertJsonPath('errors.issuance.0', 'The company does not have the minimum verified technical staff.');

    licenseStaff($company, $director->id);
    $product = Product::factory()->create();
    CompanyProduct::query()->create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'brand_name' => 'Issued Brand',
        'source' => 'own_import',
        'status' => 'approved',
        'approved_in_license_id' => null,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $preview = $this->actingAs($director, 'sanctum')->getJson("/api/v1/applications/{$id}/issue-preview")->assertOk();
    expect($preview->json('data.product_count'))->toBe(1)
        ->and($preview->json('data.documents_status'))->toBe('incomplete')
        ->and($preview->json('data.blockers'))->toBe([]);

    $issued = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])->assertOk();
    $licenseNo = 'DPPB/0001/'.now()->year;

    expect(License::query()->value('dispatch_no'))->toBe('D-14')
        ->and($issued->json('data.application.status'))->toBe('issued')
        ->and($issued->json('data.issuance.license_no'))->toBe($licenseNo)
        ->and($issued->json('data.issuance.documents_status'))->toBe('incomplete')
        ->and($company->fresh()->status)->toBe('active')
        ->and(Storage::disk('local')->exists('certificates/'.License::query()->first()->verification_token.'.pdf'))->toBeTrue()
        ->and(strlen(Storage::disk('local')->get('certificates/'.License::query()->first()->verification_token.'.pdf')))->toBeGreaterThan(100000);

    $licenseId = License::query()->value('id');
    $this->actingAs($director, 'sanctum')->getJson("/api/v1/licenses/{$licenseId}/certificate")->assertOk();

    expect(CompanyProduct::query()->where('company_id', $company->id)->value('approved_in_license_id'))->not->toBeNull();

    $token = License::query()->value('verification_token');
    $public = $this->getJson('/api/v1/public/verify/'.$token)->assertOk();
    expect($public->json('data.license_no'))->toBe($licenseNo)
        ->and($public->json('data.banner'))->toBeNull()
        ->and(json_encode($public->json()))->not->toContain('cnic');

    $this->getJson('/api/v1/public/verify/missing-token')->assertNotFound()
        ->assertJsonPath('errors.resource.0', 'Certificate not found.');

    $item = $issued->json('data.checklist.items.0.id');
    $second = $issued->json('data.checklist.items.1.id');
    $this->actingAs($director, 'sanctum')->putJson("/api/v1/applications/{$id}/checklist-items/{$item}", ['status' => 'verified'])->assertOk();
    $done = $this->actingAs($director, 'sanctum')->putJson("/api/v1/applications/{$id}/checklist-items/{$second}", ['status' => 'not_applicable'])->assertOk();
    expect($done->json('data.issuance.documents_status'))->toBe('complete')
        ->and(ActivityLog::query()->where('action', 'issued')->exists())->toBeTrue();
});

it('blocks issuance while document requirements are enforced', function () {
    $director = companyActor('Director');
    $company = Company::factory()->create();
    licenseStaff($company, $director->id);
    Setting::query()->where('key', 'enforce_document_requirements')->update(['value' => 'true']);
    $id = reachIssuance($this, $director, $company);

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])
        ->assertStatus(422)
        ->assertJsonPath('errors.issuance.0', 'Required checklist items are still missing or not verified.');
});

it('continues a renewal from the previous license and supersedes it', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $company = Company::factory()->create();
    licenseStaff($company, $director->id);
    applicationTemplate('company', 'renewal');
    $previous = applicationLicense('company', $company->id, $director->id, now()->addDays(20)->toDateString());
    $previous->license_no = 'DPPB/0048/2024';
    $previous->save();

    $created = $this->actingAs($director, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'renewal',
    ])->assertCreated();
    $id = $created->json('data.application.id');
    $submission = collect($created->json('data.stages'))->firstWhere('code', 'submission')['id'];
    $reviewed = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$submission}/complete")->assertOk();
    $progress = collect($reviewed->json('data.stages'))->firstWhere('state', 'current')['id'];
    $file = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$progress}/complete")->assertOk();
    $fileReview = collect($file->json('data.stages'))->firstWhere('state', 'current')['id'];
    $deficient = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$fileReview}/complete")->assertOk();
    $deficiency = collect($deficient->json('data.stages'))->firstWhere('state', 'current')['id'];
    $fee = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$deficiency}/skip", [
        'remarks' => 'Nothing is deficient.',
    ])->assertOk();
    $feeStage = collect($fee->json('data.stages'))->firstWhere('state', 'current')['id'];
    $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/challans", [
        'challan_no' => 'TR-REN',
        'bank_name' => 'National Bank',
        'payment_date' => now()->toDateString(),
        'amount' => 30000,
    ])->assertCreated();
    $challanId = $this->actingAs($director, 'sanctum')->getJson("/api/v1/applications/{$id}")->json('data.fees.challans.0.id');
    $this->actingAs($director, 'sanctum')->postJson("/api/v1/challans/{$challanId}/verify", ['verification_status' => 'verified'])->assertOk();
    $ready = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/stages/{$feeStage}/complete")->assertOk();

    expect($ready->json('data.application.status'))->toBe('ready_to_issue')
        ->and($ready->json('data.issuance.valid_from'))->toBe(now()->addDays(21)->toDateString());

    $issued = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])->assertOk();
    expect($issued->json('data.issuance.license_no'))->toBe('DPPB/0048/2024/R1')
        ->and($previous->fresh()->status)->toBe('superseded');
});

it('gives a new company the last issued company serial plus one', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $earlier = Company::factory()->create();
    $ignored = Company::factory()->create();
    License::query()->create([
        'license_no' => 'DPPB/0048/2024/R2',
        'licensable_type' => 'company',
        'licensable_id' => $earlier->id,
        'license_kind' => 'renewal',
        'renewal_count' => 2,
        'valid_from' => '2024-01-01',
        'valid_to' => '2024-12-31',
        'status' => 'expired',
        'verification_token' => Str::random(40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'is_legacy' => true,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    License::query()->create([
        'license_no' => 'DPP/C/2026/0099',
        'licensable_type' => 'company',
        'licensable_id' => $ignored->id,
        'license_kind' => 'registration',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => now()->addMonth()->toDateString(),
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'is_legacy' => true,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $company = Company::factory()->create();
    licenseStaff($company, $director->id);
    $id = reachIssuance($this, $director, $company);
    $issued = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-15'])->assertOk();

    expect($issued->json('data.issuance.license_no'))->toBe('DPPB/0049/'.now()->year)
        ->and((int) DB::table('license_number_sequences')->where('entity_type', 'company')->whereNull('district_id')->max('last_serial'))->toBe(49);

    $next = Company::factory()->create();
    licenseStaff($next, $director->id);
    $second = reachIssuance($this, $director, $next, false);
    $again = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$second}/issue", ['dispatch_no' => 'D-16'])->assertOk();

    expect($again->json('data.issuance.license_no'))->toBe('DPPB/0050/'.now()->year)
        ->and((int) DB::table('license_number_sequences')->where('entity_type', 'company')->whereNull('district_id')->max('last_serial'))->toBe(50);
});

it('warns when a covering document expires early and then suspends and restores the license', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $company = Company::factory()->create();
    licenseStaff($company, $director->id);
    $id = reachIssuance($this, $director, $company);
    $itemId = $this->actingAs($director, 'sanctum')->getJson("/api/v1/applications/{$id}")->json('data.checklist.items.1.id');
    $item = ApplicationChecklistItem::query()->find($itemId);
    $item->source->update(['must_cover_license_period' => true, 'requires_validity_dates' => true]);
    $type = DocumentType::factory()->create(['applies_to' => 'any', 'is_active' => true]);
    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    $file = new UploadedFile($path, 'cover.pdf', null, null, true);
    $this->actingAs($director, 'sanctum')->post("/api/v1/applications/{$id}/checklist-items/{$itemId}/documents", [
        'file' => $file,
        'document_type_id' => $type->id,
        'title' => 'Covering agreement',
        'issue_date' => now()->subYear()->toDateString(),
        'expiry_date' => now()->subDay()->toDateString(),
    ])->assertCreated();

    $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])
        ->assertStatus(409);

    $issued = $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", [
        'dispatch_no' => 'D-14',
        'confirm_warnings' => true,
        'warning_reason' => 'Accepted for this period',
    ])->assertOk();
    $licenseId = $issued->json('data.issuance.license_id');

    $this->actingAs($director, 'sanctum')->postJson("/api/v1/licenses/{$licenseId}/suspend", [
        'reason' => 'Order to suspend',
        'effective_date' => now()->toDateString(),
    ])->assertStatus(422);

    $this->actingAs($director, 'sanctum')->postJson("/api/v1/licenses/{$licenseId}/suspend", [
        'reason' => 'Order to suspend',
        'order_no' => 'SO-1',
        'effective_date' => now()->toDateString(),
    ])->assertOk();
    expect($company->fresh()->status)->toBe('suspended');

    $token = License::query()->find($licenseId)->verification_token;
    $this->getJson('/api/v1/public/verify/'.$token)->assertOk()
        ->assertJsonPath('data.banner', 'This certificate is no longer valid.');

    $this->actingAs($director, 'sanctum')->postJson("/api/v1/licenses/{$licenseId}/restore", [
        'reason' => 'Order to restore',
        'order_no' => 'SO-2',
        'effective_date' => now()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('data.status', 'active');
    expect($company->fresh()->status)->toBe('active');
});

it('limits a district officer to dealer licenses in assigned districts and still shows company licenses', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $officer = companyActor('District Officer');
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $pishin = District::factory()->create(['name' => 'Pishin', 'code' => 'PSH']);
    $officer->districts()->attach($quetta->id);
    $company = Company::factory()->create();
    licenseStaff($company, $director->id);
    $id = reachIssuance($this, $director, $company);
    $this->actingAs($director, 'sanctum')->postJson("/api/v1/applications/{$id}/issue", ['dispatch_no' => 'D-14'])->assertOk();

    $entry = companyActor('Data Entry Operator');
    $local = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $entry->id, 'updated_by' => $entry->id]);
    $other = Dealer::factory()->create(['district_id' => $pishin->id, 'created_by' => $entry->id, 'updated_by' => $entry->id]);
    License::query()->create([
        'license_no' => 'DPP/D/QTA/2026/0001',
        'licensable_type' => 'dealer',
        'licensable_id' => $local->id,
        'license_kind' => 'registration',
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->addYear()->toDateString(),
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    License::query()->create([
        'license_no' => 'DPP/D/PSH/2026/0001',
        'licensable_type' => 'dealer',
        'licensable_id' => $other->id,
        'license_kind' => 'registration',
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->addYear()->toDateString(),
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $list = $this->actingAs($officer, 'sanctum')->getJson('/api/v1/licenses')->assertOk();
    $numbers = collect($list->json('data'))->pluck('license_no');
    expect($numbers)->toContain('DPPB/0001/'.now()->year)
        ->and($numbers)->toContain('DPP/D/QTA/2026/0001')
        ->and($numbers)->not->toContain('DPP/D/PSH/2026/0001');
});

it('numbers a new dealer from the district count and the Balochistan count', function () {
    $director = companyActor('Director');
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $pishin = District::factory()->create(['name' => 'Pishin', 'code' => 'PSH']);
    $kept = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $director->id, 'updated_by' => $director->id]);
    $other = Dealer::factory()->create(['district_id' => $pishin->id, 'created_by' => $director->id, 'updated_by' => $director->id]);
    $old = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $director->id, 'updated_by' => $director->id]);
    dealerLicense($kept->id, $director->id, '0001 / DPPB - 0005 / 2025 / QTA');
    dealerLicense($other->id, $director->id, '0003 / DPPB - 0004 / 2024 / PSH');
    dealerLicense($old->id, $director->id, 'DPP/D/QTA/2026/0099');

    $numbers = app(LicenseNumbers::class);
    $first = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $director->id, 'updated_by' => $director->id]);
    $issued = $numbers->next(dealerApplication($first->id, 'new'));

    expect($issued)->toBe('0002 / DPPB - 0006 / '.now()->year.' / QTA');

    $second = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $director->id, 'updated_by' => $director->id]);
    $preview = $numbers->preview(dealerApplication($second->id, 'new'));

    expect($preview)->toBe('0003 / DPPB - 0007 / '.now()->year.' / QTA')
        ->and($numbers->next(dealerApplication($second->id, 'new')))->toBe($preview);

    $third = Dealer::factory()->create(['district_id' => $pishin->id, 'created_by' => $director->id, 'updated_by' => $director->id]);

    expect($numbers->next(dealerApplication($third->id, 'new')))->toBe('0004 / DPPB - 0008 / '.now()->year.' / PSH');

    $renewal = dealerApplication($kept->id, 'renewal');
    $renewal->previous_license_id = License::query()->where('license_no', '0001 / DPPB - 0005 / 2025 / QTA')->value('id');

    expect($numbers->next($renewal))->toBe('0001 / DPPB - 0005 / 2025 / QTA/R1')
        ->and((int) DB::table('license_number_sequences')->where('entity_type', 'dealer')->whereNull('district_id')->max('last_serial'))->toBe(8)
        ->and((int) DB::table('license_number_sequences')->where('entity_type', 'dealer')->where('district_id', $quetta->id)->max('last_serial'))->toBe(3)
        ->and((int) DB::table('license_number_sequences')->where('entity_type', 'dealer')->where('district_id', $pishin->id)->max('last_serial'))->toBe(4);
});

function dealerLicense(int $dealerId, int $userId, string $licenseNo): void
{
    License::query()->create([
        'license_no' => $licenseNo,
        'licensable_type' => 'dealer',
        'licensable_id' => $dealerId,
        'license_kind' => 'registration',
        'valid_from' => '2024-01-01',
        'valid_to' => '2024-12-31',
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'not_applicable',
        'issued_with_enforcement' => false,
        'is_legacy' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function dealerApplication(int $dealerId, string $type): LicenseApplication
{
    return new LicenseApplication([
        'licensable_type' => 'dealer',
        'licensable_id' => $dealerId,
        'application_type' => $type,
    ]);
}

it('puts the site address in the certificate qr when the domain placeholder is still set', function () {
    config(['app.frontend_url' => 'http://dpps.test:5173']);

    $url = app(CertificateQr::class)->url('abc123');

    expect($url)->toBe('http://dpps.test:5173/verify/abc123');
});

it('keeps a real public verification url in the certificate qr', function () {
    Setting::query()->where('key', 'public_verify_base_url')->update([
        'value' => 'https://dpp.example.test/verify/',
    ]);

    $url = app(CertificateQr::class)->url('abc123');

    expect($url)->toBe('https://dpp.example.test/verify/abc123');
});
