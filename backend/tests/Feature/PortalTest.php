<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\DocumentType;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->seed(WorkflowStageSeeder::class);
});

function portalCompanyUser(Company $company, string $role = 'Company Admin'): User
{
    $user = User::factory()->create([
        'user_type' => 'company',
        'company_id' => $company->id,
        'must_change_password' => false,
    ]);
    $user->assignRole($role);

    return $user;
}

function portalLicense(Company $company, int $userId, string $validTo): License
{
    return License::query()->create([
        'license_no' => 'DPP-'.Str::upper(Str::random(8)),
        'licensable_type' => 'company',
        'licensable_id' => $company->id,
        'license_kind' => 'registration',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => $validTo,
        'issued_at' => now()->subYear(),
        'status' => 'active',
        'verification_token' => Str::random(40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function portalPdf(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

    return new UploadedFile($path, 'letter.pdf', null, null, true);
}

it('keeps a company user inside their own company', function () {
    $home = Company::factory()->create(['name' => 'Home Agro']);
    $other = Company::factory()->create(['name' => 'Other Agro']);
    $admin = portalCompanyUser($home);
    $staff = portalCompanyUser($home, 'Company Staff');
    $outsider = portalCompanyUser($other);
    $officer = User::factory()->create(['must_change_password' => false, 'user_type' => 'staff']);
    $officer->assignRole('Registration Officer');
    CompanyPerson::factory()->create([
        'company_id' => $other->id,
        'role' => 'technical_staff',
        'source' => 'portal',
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);
    $foreign = LicenseApplication::query()->create([
        'application_no' => 'APP-C-2026-9001',
        'licensable_type' => 'company',
        'licensable_id' => $other->id,
        'application_type' => 'renewal',
        'checklist_template_id' => ChecklistTemplate::factory()->create([
            'entity_type' => 'company',
            'application_type' => 'renewal',
            'status' => 'published',
        ])->id,
        'submitted_via' => 'office',
        'submitted_by_user_id' => $officer->id,
        'status' => 'submitted',
        'fee_amount' => 0,
        'penalty_total' => 0,
        'total_payable' => 0,
        'created_by' => $officer->id,
        'updated_by' => $officer->id,
    ]);

    $this->actingAs($officer, 'sanctum')->getJson('/api/v1/portal/dashboard')->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/portal/company')
        ->assertOk()
        ->assertJsonPath('data.company.name', 'Home Agro')
        ->assertJsonPath('data.contact_message', 'To change any of this information, please contact the Directorate of Plant Protection: 081-9211868, dppb2018@gmail.com');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Home Agro'])
        ->assertJsonMissing(['name' => 'Other Agro']);

    $this->actingAs($admin, 'sanctum')->getJson("/api/v1/companies/{$other->id}")->assertNotFound();
    $this->actingAs($admin, 'sanctum')->getJson("/api/v1/companies/{$other->id}/people")->assertNotFound();
    $this->actingAs($admin, 'sanctum')->getJson("/api/v1/companies/{$other->id}/products")->assertNotFound();
    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/dealers')->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/portal/staff')
        ->assertOk()
        ->assertJsonMissing(['company_id' => $other->id]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/portal/applications/{$foreign->id}")
        ->assertNotFound();

    $this->actingAs($outsider, 'sanctum')
        ->getJson('/api/v1/portal/company')
        ->assertOk()
        ->assertJsonPath('data.company.name', 'Other Agro');

    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/portal/users')->assertForbidden();
});

it('saves portal staff documents and products as pending and tells the officers', function () {
    Storage::fake('local');
    $company = Company::factory()->create();
    $admin = portalCompanyUser($company);
    $officer = User::factory()->create(['must_change_password' => false, 'user_type' => 'staff']);
    $officer->assignRole('Director');
    $product = Product::factory()->create();
    $type = DocumentType::factory()->create(['applies_to' => 'company']);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/staff', [
        'role' => 'ceo',
        'cnic' => '5440011100001',
        'full_name' => 'Portal Person',
        'mobile' => '03001112221',
        'start_date' => '2024-02-01',
    ])->assertCreated();

    $assignment = CompanyPerson::query()->where('company_id', $company->id)->first();
    expect($assignment->role)->toBe('technical_staff')
        ->and($assignment->source)->toBe('portal')
        ->and($assignment->verification_status)->toBe('pending')
        ->and($officer->fresh()->notifications)->toHaveCount(1);

    $this->actingAs($admin, 'sanctum')->post('/api/v1/portal/documents', [
        'title' => 'Portal letter',
        'document_type_id' => $type->id,
        'attested_by' => 'none',
        'file' => portalPdf(),
    ])->assertCreated()
        ->assertJsonPath('data.verification_status', 'pending')
        ->assertJsonPath('data.uploaded_via', 'portal');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/products', [
        'brand_name' => 'Portal Brand',
        'product_id' => $product->id,
        'source' => 'own_import',
        'sample_provided' => false,
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    expect($officer->fresh()->notifications)->toHaveCount(3);

    $this->actingAs(portalCompanyUser(Company::factory()->create()), 'sanctum')
        ->postJson("/api/v1/portal/staff/{$assignment->id}/end", [
            'end_date' => '2024-06-01',
            'end_reason' => 'Left the company',
        ])->assertNotFound();
});

it('lets a company admin manage only their own users and submit a renewal inside the window', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $admin = portalCompanyUser($company);
    $officer = User::factory()->create(['must_change_password' => false, 'user_type' => 'staff']);
    $officer->assignRole('Registration Officer');
    $template = ChecklistTemplate::factory()->create([
        'entity_type' => 'company',
        'application_type' => 'renewal',
        'status' => 'published',
        'published_at' => now(),
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'portal_uploadable' => true,
        'requires_upload' => true,
    ]);
    portalLicense($company, $admin->id, now()->addDays(90)->toDateString());

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/renewal', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.application_type.0', 'A renewal cannot be submitted before the renewal window opens.');

    License::query()->where('licensable_id', $company->id)->update([
        'valid_to' => now()->addDays(20)->toDateString(),
    ]);

    $started = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/renewal', []);
    $started->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.submitted_via', 'portal');

    $this->actingAs(portalCompanyUser($company, 'Company Staff'), 'sanctum')
        ->postJson('/api/v1/portal/renewal/submit', [
            'declarant_name' => 'Staff Person',
            'declarant_cnic' => '5440011100002',
            'total_pages' => 12,
            'certified' => true,
        ])->assertForbidden();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/renewal/submit', [])
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/renewal/submit', [
        'declarant_name' => 'Home Admin',
        'declarant_cnic' => '5440011100002',
        'total_pages' => 12,
        'certified' => true,
    ])->assertOk()->assertJsonPath('data.status', 'submitted');

    $application = LicenseApplication::query()->where('licensable_id', $company->id)->first();
    expect($application->submitted_via)->toBe('portal')
        ->and($application->current_stage_id)->not->toBeNull()
        ->and($application->total_pages)->toBe(12)
        ->and($officer->fresh()->notifications()->where('data->kind', 'portal_submission')->exists())->toBeTrue();

    $this->actingAs(portalCompanyUser($other), 'sanctum')
        ->getJson("/api/v1/portal/applications/{$application->id}")
        ->assertNotFound();

    $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/portal/users', [
        'name' => 'Second Admin',
        'email' => 'second.admin@example.com',
        'mobile' => '03001112222',
        'password' => 'Portal1234',
        'password_confirmation' => 'Portal1234',
        'role' => 'Company Staff',
    ]);
    $created->assertCreated();
    $member = User::query()->where('email', 'second.admin@example.com')->first();
    expect($member->company_id)->toBe($company->id)
        ->and($member->must_change_password)->toBeTrue();

    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/portal/users/{$member->id}/reset-password", [
        'password' => 'Portal5678',
        'password_confirmation' => 'Portal5678',
    ])->assertOk();
    expect($member->fresh()->must_change_password)->toBeTrue();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/portal/users/{$member->id}/deactivate", [])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->actingAs(portalCompanyUser($other), 'sanctum')
        ->postJson("/api/v1/portal/users/{$member->id}/deactivate", [])
        ->assertNotFound();
});
