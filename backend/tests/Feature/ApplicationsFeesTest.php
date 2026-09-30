<?php

use App\Models\ActivityLog;
use App\Models\ChallanItem;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\Dealer;
use App\Models\District;
use App\Models\DocumentType;
use App\Models\License;
use App\Models\Person;
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

function feeRates(): void
{
    foreach ([
        ['company', 'registration', 50000],
        ['company', 'renewal', 30000],
        ['company', 'late_renewal_per_day', 500],
        ['company', 'no_technical_staff_per_month', 40000],
        ['dealer', 'registration', 5000],
        ['dealer', 'late_renewal_per_day', 500],
    ] as [$entity, $type, $amount]) {
        DB::table('fee_structures')->insert([
            'entity_type' => $entity,
            'fee_type' => $type,
            'amount' => $amount,
            'effective_from' => '2020-01-01',
            'notes' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('shows helper text and records a penalty only when an officer enters it', function () {
    feeRates();
    $director = companyActor('Director');
    $officer = companyActor('Registration Officer');
    $entry = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    applicationTemplate('company', 'renewal');
    License::query()->create([
        'license_no' => 'LIC-'.Str::upper(Str::random(8)),
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'license_kind' => 'registration',
        'valid_from' => '2024-01-01',
        'valid_to' => now()->subDays(12)->toDateString(),
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'person_id' => Person::factory()->create(['cnic_pending' => false, 'created_by' => $director->id, 'updated_by' => $director->id])->id,
        'verification_status' => 'pending',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $created = $this->actingAs($director, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'renewal',
    ])->assertCreated();

    $id = $created->json('data.application.id');
    $lateDays = $created->json('data.application.late_days');

    expect($created->json('data.fees.quote.amount'))->toBe('30000.00')
        ->and($created->json('data.fees.quote.label'))->toBe('Renewal fee')
        ->and($created->json('data.fees.recorded'))->toBeFalse()
        ->and($created->json('data.fees.penalties'))->toBe([])
        ->and($created->json('data.fees.helpers.0.text'))->toBe("Submitted {$lateDays} days after expiry.")
        ->and($created->json('data.fees.rates.late_renewal.amount'))->toBe('500.00')
        ->and(collect($created->json('data.fees.helpers'))->pluck('penalty_type'))->toContain('no_technical_staff');

    $this->actingAs($entry, 'sanctum')->postJson("/api/v1/applications/{$id}/penalties", [
        'penalty_type' => 'other',
        'basis' => 'Manual',
        'final_amount' => 100,
    ])->assertForbidden();

    $this->actingAs($officer, 'sanctum')->postJson("/api/v1/applications/{$id}/penalties", [
        'penalty_type' => 'late_renewal',
        'basis' => '12 days x 500',
        'standard_amount' => 6000,
        'final_amount' => 3000,
    ])->assertStatus(422)
        ->assertJsonPath('errors.waiver_reason.0', 'Enter the waiver reason.');

    $entered = $this->actingAs($officer, 'sanctum')->postJson("/api/v1/applications/{$id}/penalties", [
        'penalty_type' => 'late_renewal',
        'basis' => '12 days x 500',
        'standard_amount' => 6000,
        'final_amount' => 3000,
        'waiver_reason' => 'Partial waiver approved in principle',
        'waiver_order_no' => 'SO-12',
    ])->assertCreated();

    $penaltyId = $entered->json('data.fees.penalties.0.id');

    expect($entered->json('data.fees.penalties.0.is_waived_or_reduced'))->toBeTrue()
        ->and($entered->json('data.fees.penalties.0.waiver_approved'))->toBeFalse()
        ->and($entered->json('data.fees.penalties.0.reference_rate'))->toEqual(500)
        ->and($entered->json('data.application.penalty_total'))->toEqual(3000)
        ->and($entered->json('data.fees.total_payable'))->toEqual(3000);

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/penalties/{$penaltyId}/approve-waiver")
        ->assertForbidden();

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/penalties/{$penaltyId}/approve-waiver")
        ->assertOk()
        ->assertJsonPath('data.fees.penalties.0.waiver_approved', true)
        ->assertJsonPath('data.fees.penalties.0.waiver_approved_by', $director->name);

    expect(ActivityLog::query()->where('action', 'penalty_entered')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'penalty_waived')->exists())->toBeTrue();
});

it('blocks the fee stage until a reduced penalty is approved and verifies a challan', function () {
    Storage::fake('local');
    feeRates();
    $director = companyActor('Director');
    $officer = companyActor('Registration Officer');
    $company = Company::factory()->create();
    applicationTemplate('company', 'new');
    $type = DocumentType::factory()->create(['applies_to' => 'any', 'name' => 'Treasury challan', 'is_active' => true]);

    $created = $this->actingAs($director, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'new',
    ])->assertCreated();
    $id = $created->json('data.application.id');
    $submission = collect($created->json('data.stages'))->firstWhere('code', 'submission')['id'];
    $reviewed = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$submission}/complete")
        ->assertOk();
    $fileReview = collect($reviewed->json('data.stages'))->firstWhere('state', 'current')['id'];
    $deficient = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$fileReview}/complete")
        ->assertOk();
    $deficiency = collect($deficient->json('data.stages'))->firstWhere('state', 'current')['id'];
    $fee = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$deficiency}/skip", ['remarks' => 'Nothing is deficient.'])
        ->assertOk();
    $feeStage = collect($fee->json('data.stages'))->firstWhere('state', 'current')['id'];

    $entered = $this->actingAs($officer, 'sanctum')->postJson("/api/v1/applications/{$id}/penalties", [
        'penalty_type' => 'other',
        'basis' => 'Manual reduction',
        'standard_amount' => 1000,
        'final_amount' => 400,
        'waiver_reason' => 'Reduced by order',
    ])->assertCreated();
    $penaltyId = $entered->json('data.fees.penalties.0.id');

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$feeStage}/complete")
        ->assertStatus(422)
        ->assertJsonPath('errors.stage.0', 'Approve the reduced penalty before completing this stage.');

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/penalties/{$penaltyId}/approve-waiver")
        ->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'dpps');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    $file = new UploadedFile($path, 'challan.pdf', null, null, true);

    $recorded = $this->actingAs($officer, 'sanctum')->post("/api/v1/applications/{$id}/challans", [
        'challan_no' => 'TR-100',
        'bank_name' => 'National Bank',
        'branch' => 'Quetta',
        'payment_date' => '2026-09-01',
        'amount' => 50400,
        'document_type_id' => $type->id,
        'file' => $file,
    ])->assertCreated();

    $challanId = $recorded->json('data.fees.challans.0.id');

    expect($recorded->json('data.fees.challans.0.verification_status'))->toBe('pending')
        ->and($recorded->json('data.fees.paid_verified'))->toBe('0.00')
        ->and($recorded->json('data.fees.challans.0.document.title'))->toBe('Treasury challan')
        ->and(ChallanItem::query()->where('challan_id', $challanId)->value('item_type'))->toBe('registration_fee');

    $this->actingAs($officer, 'sanctum')->post("/api/v1/applications/{$id}/challans", [
        'challan_no' => 'TR-100',
        'bank_name' => 'National Bank',
        'payment_date' => '2026-09-01',
        'amount' => 1,
    ])->assertStatus(422)
        ->assertJsonPath('errors.challan_no.0', 'This challan number is already used.');

    $verified = $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/challans/{$challanId}/verify", ['verification_status' => 'verified'])
        ->assertOk();

    expect($verified->json('data.fees.challans.0.verification_status'))->toBe('verified')
        ->and($verified->json('data.fees.paid_verified'))->toEqual(50400);

    $completed = $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/applications/{$id}/stages/{$feeStage}/complete")
        ->assertOk();

    expect($completed->json('data.application.fee_amount'))->toEqual(50000)
        ->and($completed->json('data.application.total_payable'))->toEqual(50400)
        ->and($completed->json('data.fees.balance'))->toEqual(0)
        ->and($completed->json('data.application.current_stage.code'))->toBe('issuance');
});

it('lets a district officer enter a penalty only for a dealer in an assigned district', function () {
    feeRates();
    $entry = companyActor('Data Entry Operator');
    $officer = companyActor('District Officer');
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $officer->districts()->attach($quetta->id);
    $company = Company::factory()->create();
    $dealer = Dealer::factory()->create(['district_id' => $quetta->id, 'created_by' => $entry->id, 'updated_by' => $entry->id]);
    applicationTemplate('company', 'new');
    applicationTemplate('dealer', 'new');

    $companyApp = $this->actingAs($entry, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'application_type' => 'new',
    ])->assertCreated();
    $dealerApp = $this->actingAs($entry, 'sanctum')->postJson('/api/v1/applications', [
        'licensable_type' => 'dealer',
        'licensable_id' => $dealer->id,
        'application_type' => 'new',
    ])->assertCreated();

    $this->actingAs($officer, 'sanctum')->postJson('/api/v1/applications/'.$companyApp->json('data.application.id').'/penalties', [
        'penalty_type' => 'other',
        'basis' => 'Manual',
        'final_amount' => 10,
    ])->assertNotFound();

    $this->actingAs($officer, 'sanctum')->postJson('/api/v1/applications/'.$dealerApp->json('data.application.id').'/penalties', [
        'penalty_type' => 'other',
        'basis' => 'Manual',
        'final_amount' => 10,
    ])->assertCreated()
        ->assertJsonPath('data.fees.rates.no_technical_staff', null)
        ->assertJsonPath('data.fees.penalties.0.final_amount', '10.00');
});
