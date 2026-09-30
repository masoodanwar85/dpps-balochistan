<?php

use App\Models\ActivityLog;
use App\Models\ApplicationStageLog;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\District;
use App\Models\DocumentType;
use App\Models\License;
use App\Models\LicenseApplication;
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
});

function applicationTemplate(string $entity, string $type): ChecklistTemplate
{
    $template = ChecklistTemplate::factory()->create([
        'entity_type' => $entity,
        'application_type' => $type,
        'version_no' => 1,
        'name' => ucfirst($entity).' '.$type,
        'status' => 'published',
        'published_at' => now(),
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'sort_order' => 1,
        'annex_code' => 'A',
        'title' => 'Application form',
        'is_required' => true,
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'sort_order' => 2,
        'annex_code' => 'S',
        'title' => 'Agreement',
        'requires_validity_dates' => true,
        'max_files' => 1,
    ]);

    return $template;
}

function applicationLicense(string $type, int $id, int $userId, string $validTo): License
{
    return License::query()->create([
        'license_no' => 'LIC-'.Str::upper(Str::random(8)),
        'licensable_type' => $type,
        'licensable_id' => $id,
        'license_kind' => 'registration',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => $validTo,
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

it('numbers a new application, locks the checklist, and blocks a second open application', function () {
    $entry = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    applicationTemplate('company', 'new');

    $created = $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'new',
            'diary_no' => '1123',
        ]);

    $created->assertCreated()
        ->assertJsonPath('data.application.application_no', 'APP-C-'.now()->year.'-0001')
        ->assertJsonPath('data.application.status', 'submitted')
        ->assertJsonPath('data.application.current_stage.code', 'submission')
        ->assertJsonPath('data.checklist.total', 2)
        ->assertJsonPath('data.checklist.items.0.status', 'pending');

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'new',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.licensable_id.0', 'This company already has an open application.');

    expect(ActivityLog::query()->where('subject_type', 'license_application')->where('action', 'created')->exists())->toBeTrue();
});

it('blocks a renewal before the window and records late days after expiry', function () {
    $entry = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    applicationTemplate('company', 'renewal');
    applicationLicense('company', $company->id, $entry->id, now()->addYear()->toDateString());

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'renewal',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.application_type.0', 'A renewal cannot be submitted before the renewal window opens.');

    License::query()->where('licensable_id', $company->id)->update([
        'valid_to' => now()->subDays(12)->toDateString(),
    ]);

    $this->actingAs($entry, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'renewal',
        ])
        ->assertCreated()
        ->assertJsonPath('data.application.application_type', 'renewal')
        ->assertJsonPath('data.application.late_days', 12);
});

it('skips stages that do not apply or are inactive and stops the fee stage without a fee', function () {
    $director = companyActor('Director');
    $company = Company::factory()->create();
    applicationTemplate('company', 'new');

    $created = $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'new',
        ])
        ->assertCreated();

    $id = $created->json('data.application.id');
    $submission = collect($created->json('data.stages'))->firstWhere('code', 'submission');

    $reviewed = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$submission['id']}/complete", []);

    $reviewed->assertOk()
        ->assertJsonPath('data.application.current_stage.code', 'file_review')
        ->assertJsonPath('data.application.status', 'under_review');

    $progress = collect($reviewed->json('data.stages'))->firstWhere('code', 'progress_review');
    expect($progress['state'])->toBe('skipped');

    $entry = companyActor('Data Entry Operator');
    $fileReview = collect($reviewed->json('data.stages'))->firstWhere('state', 'current');
    $this->actingAs($entry, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$fileReview['id']}/complete", [])
        ->assertForbidden();

    $deficient = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$fileReview['id']}/complete", [])
        ->assertOk();

    expect($deficient->json('data.application.current_stage.code'))->toBe('deficiency');

    $deficiency = collect($deficient->json('data.stages'))->firstWhere('state', 'current');
    $fee = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$deficiency['id']}/skip", [
            'remarks' => 'Nothing is deficient.',
        ])
        ->assertOk();

    expect($fee->json('data.application.current_stage.code'))->toBe('fee')
        ->and($fee->json('data.application.status'))->toBe('fee_pending');

    $feeStage = collect($fee->json('data.stages'))->firstWhere('state', 'current');
    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$feeStage['id']}/complete", [])
        ->assertStatus(422)
        ->assertJsonPath('errors.stage.0', 'Fee is not configured for this date. Contact the system administrator.');

    DB::table('fee_structures')->insert([
        'entity_type' => 'company',
        'fee_type' => 'registration',
        'amount' => 50000,
        'effective_from' => '2020-01-01',
        'notes' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $issued = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$feeStage['id']}/complete", [])
        ->assertOk();

    expect($issued->json('data.application.current_stage.code'))->toBe('issuance')
        ->and($issued->json('data.application.status'))->toBe('fee_pending')
        ->and($issued->json('data.application.fee_amount'))->toEqual(50000)
        ->and(collect($issued->json('data.stages'))->firstWhere('code', 'higher_approval')['state'])->toBe('skipped');

    $log = ApplicationStageLog::query()->where('application_id', $id)->whereNull('completed_at')->first();
    $log->update([
        'entered_at' => now()->subDays(5),
        'due_at' => now()->subDay(),
    ]);

    $this->actingAs($director, 'sanctum')
        ->getJson("/api/v1/applications/{$id}")
        ->assertOk()
        ->assertJsonPath('data.application.sla_breached', true);
});

it('keeps a district officer to dealer applications in assigned districts', function () {
    $entry = companyActor('Data Entry Operator');
    $officer = companyActor('District Officer');
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $pishin = District::factory()->create(['name' => 'Pishin', 'code' => 'PSH']);
    $officer->districts()->attach($quetta->id);
    $company = Company::factory()->create();
    $local = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $entry->id, 'updated_by' => $entry->id]);
    $other = Dealer::factory()->create(['district_id' => $pishin->id, 'created_by' => $entry->id, 'updated_by' => $entry->id]);
    applicationTemplate('company', 'new');
    applicationTemplate('dealer', 'new');

    $companyApp = $this->actingAs($entry, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'new',
    ])->assertCreated();

    $dealerApp = $this->actingAs($entry, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'dealer',
        'licensable_id' => $local->id,
        'application_type' => 'new',
    ])->assertCreated();

    $this->actingAs($entry, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'dealer',
        'licensable_id' => $other->id,
        'application_type' => 'new',
    ])->assertCreated();

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/applications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.application_no', 'APP-D-'.now()->year.'-0001');

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/applications/'.$companyApp->json('data.application.id'))
        ->assertNotFound();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'dealer',
            'licensable_id' => $local->id,
            'application_type' => 'new',
        ])
        ->assertForbidden();

    expect($dealerApp->json('data.application.application_no'))->toBe('APP-D-'.now()->year.'-0001');
});

it('updates the checklist, issues a deficiency letter, and lets a withdrawn application be replaced', function () {
    Storage::fake('local');
    $director = companyActor('Director');
    $company = Company::factory()->create();
    applicationTemplate('company', 'new');
    $type = DocumentType::factory()->create(['applies_to' => 'any', 'name' => 'Application form']);

    $created = $this->actingAs($director, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'new',
    ])->assertCreated();

    $id = $created->json('data.application.id');
    $item = $created->json('data.checklist.items.0.id');
    $submission = collect($created->json('data.stages'))->firstWhere('code', 'submission')['id'];

    $this->actingAs($director, 'sanctum')
        ->putJson("/api/v1/applications/{$id}/checklist-items/{$item}", [
            'status' => 'deficient',
        ])
        ->assertStatus(422);

    $this->actingAs($director, 'sanctum')
        ->putJson("/api/v1/applications/{$id}/checklist-items/{$item}", [
            'status' => 'deficient',
            'officer_remarks' => 'Signature is missing.',
            'page_count' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist.items.0.status', 'deficient');

    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    $file = new UploadedFile($path, 'form.pdf', null, null, true);

    $this->actingAs($director, 'sanctum')
        ->post("/api/v1/applications/{$id}/checklist-items/{$item}/documents", [
            'file' => $file,
            'document_type_id' => $type->id,
            'title' => 'Signed form',
        ])
        ->assertCreated()
        ->assertJsonPath('data.checklist.items.0.status', 'submitted');

    $this->actingAs($director, 'sanctum')
        ->putJson("/api/v1/applications/{$id}/checklist-items/{$item}", [
            'status' => 'verified',
            'page_count' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist.done', 1);

    $second = $created->json('data.checklist.items.1.id');
    $this->actingAs($director, 'sanctum')
        ->putJson("/api/v1/applications/{$id}/checklist-items/{$second}", [
            'status' => 'not_applicable',
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist.done', 2);

    $this->actingAs($director, 'sanctum')
        ->putJson("/api/v1/applications/{$id}/checklist-items/{$item}", [
            'status' => 'deficient',
            'officer_remarks' => 'Signature is missing.',
        ])
        ->assertOk();

    $reviewed = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$submission}/complete", [])
        ->assertOk();
    $fileReview = collect($reviewed->json('data.stages'))->firstWhere('state', 'current')['id'];
    $deficient = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$fileReview}/complete", [])
        ->assertOk();
    expect($deficient->json('data.application.current_stage.code'))->toBe('deficiency');

    $letter = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/deficiency-letters", [])
        ->assertCreated();

    expect($letter->json('data.application.status'))->toBe('deficiency_issued')
        ->and($letter->json('data.letters.0.letter_no'))->toBe('DEF-'.now()->year.'-0001')
        ->and(Storage::disk('local')->exists('deficiency-letters/DEF-'.now()->year.'-0001.pdf'))->toBeTrue();

    $letterId = $letter->json('data.letters.0.id');
    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/deficiency-letters/{$letterId}/resolve", [])
        ->assertOk()
        ->assertJsonPath('data.application.status', 'under_review')
        ->assertJsonPath('data.letters.0.status', 'resolved');

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/withdraw", ['reason' => 'Filed in error'])
        ->assertOk()
        ->assertJsonPath('data.application.status', 'withdrawn');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/applications', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_type' => 'new',
        ])
        ->assertCreated()
        ->assertJsonPath('data.application.application_no', 'APP-C-'.now()->year.'-0002');

    expect(LicenseApplication::query()->find($id)->status)->toBe('withdrawn');
});
