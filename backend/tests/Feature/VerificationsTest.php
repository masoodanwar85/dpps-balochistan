<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Companies\CompanyProfile;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function queueDocument(string $ownerType, int $ownerId, int $userId, string $via = 'portal'): Document
{
    return Document::query()->create([
        'documentable_type' => $ownerType,
        'documentable_id' => $ownerId,
        'document_type_id' => DocumentType::factory()->create()->id,
        'title' => 'Portal letter '.$ownerId,
        'file_path' => 'documents/letter.pdf',
        'original_name' => 'letter.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', $ownerType.$ownerId.$via),
        'attested_by' => 'none',
        'version_no' => 1,
        'uploaded_by' => $userId,
        'uploaded_via' => $via,
        'verification_status' => 'pending',
    ]);
}

it('lists pending staff and lets a registration officer approve or reject them', function () {
    $officer = companyActor('Registration Officer');
    $entry = companyActor('Data Entry Operator');
    $company = Company::factory()->create();
    $member = User::factory()->create([
        'user_type' => 'company',
        'company_id' => $company->id,
        'must_change_password' => false,
    ]);
    $member->assignRole('Company Admin');
    $pending = CompanyPerson::factory()->create([
        'company_id' => $company->id,
        'source' => 'portal',
        'verification_status' => 'pending',
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);
    $other = Company::factory()->create();
    $hidden = CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'source' => 'portal',
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);

    $this->actingAs($entry, 'sanctum')
        ->getJson('/api/v1/verifications?type=staff')
        ->assertForbidden();

    $list = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/verifications?type=staff');

    $list->assertOk()
        ->assertJsonPath('meta.counts.staff', 2)
        ->assertJsonFragment(['id' => $pending->id, 'item' => 'New staff: '.$pending->person->full_name]);

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/staff/{$pending->id}/reject", [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason.0', 'Enter a reason.');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/staff/{$pending->id}/approve")
        ->assertOk();

    expect($pending->fresh()->verification_status)->toBe('verified')
        ->and(app(CompanyProfile::class)->verifiedTechnicalStaff($company))->toBe(1)
        ->and(ActivityLog::query()->where('subject_id', $pending->id)->where('action', 'approved')->exists())->toBeTrue()
        ->and($member->fresh()->notifications)->toHaveCount(1);

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/staff/{$hidden->id}/reject", ['reason' => 'CNIC does not match the form.'])
        ->assertOk();

    expect($hidden->fresh()->verification_status)->toBe('rejected')
        ->and($hidden->fresh()->rejection_reason)->toBe('CNIC does not match the form.')
        ->and(app(CompanyProfile::class)->verifiedTechnicalStaff($other))->toBe(0);

    $stillPending = CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'source' => 'portal',
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);
    $outsider = companyActor('Company Admin', [
        'user_type' => 'company',
        'company_id' => $company->id,
    ]);
    $outsider->givePermissionTo('staff.verify');

    $this->actingAs($officer, 'sanctum')
        ->getJson("/api/v1/verifications/staff/{$stillPending->id}")
        ->assertOk();

    $this->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/verifications/staff/{$stillPending->id}")
        ->assertNotFound();
});

it('keeps dealer documents inside a district officer district and approves products without a license', function () {
    $officer = companyActor('District Officer');
    $director = companyActor('Director');
    $home = District::factory()->create();
    $away = District::factory()->create();
    $officer->districts()->attach($home->id);
    $local = Dealer::factory()->create([
        'district_id' => $home->id,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    $remote = Dealer::factory()->create([
        'district_id' => $away->id,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    $localDocument = queueDocument('dealer', $local->id, $director->id);
    $remoteDocument = queueDocument('dealer', $remote->id, $director->id);
    $company = Company::factory()->create();
    $product = CompanyProduct::query()->create([
        'company_id' => $company->id,
        'brand_name' => 'Queue Brand',
        'source' => 'own_import',
        'sample_provided' => false,
        'status' => 'pending',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/verifications/staff/1/approve')
        ->assertForbidden();

    $documents = $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/verifications?type=document');

    $documents->assertOk()
        ->assertJsonFragment(['id' => $localDocument->id])
        ->assertJsonMissing(['id' => $remoteDocument->id]);

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/document/{$remoteDocument->id}/reject", ['reason' => 'Wrong district.'])
        ->assertNotFound();

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/document/{$localDocument->id}/approve")
        ->assertOk();

    expect($localDocument->fresh()->verification_status)->toBe('verified');

    $this->actingAs($officer, 'sanctum')
        ->postJson("/api/v1/verifications/product/{$product->id}/approve")
        ->assertOk();

    expect($product->fresh()->status)->toBe('approved')
        ->and($product->fresh()->approved_in_license_id)->toBeNull();

    $second = CompanyProduct::query()->create([
        'company_id' => $company->id,
        'brand_name' => 'Rejected Brand',
        'source' => 'own_import',
        'sample_provided' => true,
        'status' => 'pending',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $this->actingAs($director, 'sanctum')
        ->postJson("/api/v1/verifications/product/{$second->id}/reject", ['reason' => 'Sample label is unreadable.'])
        ->assertOk();

    expect($second->fresh()->status)->toBe('withdrawn')
        ->and($second->fresh()->remarks)->toBe('Sample label is unreadable.')
        ->and(ActivityLog::query()->where('subject_type', 'company_product')->where('subject_id', $second->id)->where('action', 'rejected')->exists())->toBeTrue();
});
