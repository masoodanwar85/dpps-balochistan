<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\License;
use App\Models\Person;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function previousGrant(string $type, int $id, int $userId, string $validTo, string $status = 'expired'): License
{
    return License::query()->create([
        'license_no' => 'OLD-'.Str::upper(Str::random(8)),
        'licensable_type' => $type,
        'licensable_id' => $id,
        'license_kind' => 'registration',
        'valid_from' => Carbon::parse($validTo)->subYear()->toDateString(),
        'valid_to' => $validTo,
        'issued_at' => Carbon::parse($validTo)->subYear(),
        'status' => $status,
        'verification_token' => Str::random(40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

it('records a previous company license up to the last day of this year', function () {
    $director = companyActor('Director');
    $company = Company::factory()->create(['status' => 'unlicensed']);
    $end = now()->endOfYear()->toDateString();
    $start = Carbon::parse($end)->subMonths(12)->addDay()->toDateString();

    $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/licenses/previous?licensable_type=company&valid_to='.$end)
        ->assertOk()
        ->assertJsonPath('data.latest_end_date', $end)
        ->assertJsonPath('data.valid_from', $start);

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => '905 /PP/DGA',
            'license_kind' => 'renewal',
            'valid_to' => $end,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.dispatch_no.0', 'Enter the dispatch number.');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => '905 /PP/DGA',
            'license_kind' => 'renewal',
            'dispatch_no' => 'D-14',
            'valid_to' => $end,
        ])
        ->assertCreated()
        ->assertJsonPath('data.license_no', '905 /PP/DGA')
        ->assertJsonPath('data.dispatch_no', 'D-14')
        ->assertJsonPath('data.valid_from', $start)
        ->assertJsonPath('data.valid_to', $end)
        ->assertJsonPath('data.license_kind', 'renewal')
        ->assertJsonPath('data.documents_status', 'not_applicable')
        ->assertJsonPath('data.application_id', null);

    $license = License::query()->where('license_no', '905 /PP/DGA')->first();
    expect($license)->not->toBeNull()
        ->and($license->is_legacy)->toBeTrue()
        ->and($license->renewal_count)->toBe(1)
        ->and($license->certificate_path)->toBeNull()
        ->and($license->status)->toBe('active')
        ->and($company->fresh()->status)->toBeIn(['active', 'expiring'])
        ->and(ActivityLog::query()->where('action', 'created')->where('subject_type', 'license')->where('subject_id', $license->id)->exists())->toBeTrue();

    $download = $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/licenses/'.$license->id.'/certificate')
        ->assertOk();
    expect($download->json('data.url'))->toContain($license->verification_token)
        ->and($license->fresh()->certificate_path)->toBeNull();
});

it('rejects an end date after this year and a repeated license number', function () {
    $director = companyActor('Director');
    $company = Company::factory()->create();
    $message = 'The end date must be '.now()->endOfYear()->format('d-m-Y').' or earlier.';

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => 'NEXT-YEAR',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->endOfYear()->addDay()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.valid_to.0', $message);

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => 'PAST-1',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->subYear()->endOfYear()->toDateString(),
        ])
        ->assertCreated();

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => 'PAST-1',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->subYears(2)->endOfYear()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.license_no.0', 'This license number is already on file.');
});

it('records a previous dealer license and leaves a suspended company unchanged', function () {
    $director = companyActor('Director');
    $dealer = Dealer::factory()->create(['status' => 'unlicensed']);
    $end = now()->subYear()->endOfYear()->toDateString();

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'dealer',
            'licensable_id' => $dealer->id,
            'license_no' => 'DLR-100',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => $end,
        ])
        ->assertCreated()
        ->assertJsonPath('data.licensable_type', 'dealer')
        ->assertJsonPath('data.status', 'expired');

    expect($dealer->fresh()->status)->toBe('expired');

    $company = Company::factory()->create(['status' => 'suspended']);

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => 'SUS-1',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->endOfYear()->toDateString(),
        ])
        ->assertCreated();

    expect($company->fresh()->status)->toBe('suspended');
});

it('keeps a later license as the current one and supersedes an earlier grant', function () {
    $director = companyActor('Director');
    $current = Company::factory()->create(['status' => 'active']);
    $later = previousGrant('company', $current->id, $director->id, now()->addYear()->endOfYear()->toDateString(), 'active');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $current->id,
            'license_no' => 'MID-1',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->subYear()->endOfYear()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'expired');

    expect($later->fresh()->status)->toBe('active')
        ->and($current->fresh()->status)->toBe('active');

    $company = Company::factory()->create(['status' => 'expired']);
    $earlier = previousGrant('company', $company->id, $director->id, now()->subYears(2)->endOfYear()->toDateString());

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'license_no' => 'MID-2',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->subYear()->endOfYear()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'expired');

    expect($earlier->fresh()->status)->toBe('superseded')
        ->and($company->fresh()->status)->toBe('expired');
});

it('prints a dealer certificate without a stored file', function () {
    $director = companyActor('Director');
    $dealer = Dealer::factory()->create([
        'shop_name' => 'Ahmed Agro',
        'mobile' => '03001234567',
        'business_address' => 'Jinnah Road Quetta',
    ]);
    $person = Person::factory()->create([
        'full_name' => 'Ahmed Dealer',
        'father_name' => 'Khan',
        'cnic' => '1234512345123',
    ]);
    DealerOwner::factory()->create([
        'dealer_id' => $dealer->id,
        'person_id' => $person->id,
    ]);
    $license = previousGrant('dealer', $dealer->id, $director->id, now()->endOfYear()->toDateString(), 'active');
    $license->license_no = 'DPP/D/QTA/2026/0001';
    $license->save();

    $full = $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/licenses/'.$license->id.'/certificate')
        ->assertOk();

    expect($full->json('data.url'))->toContain($license->verification_token)
        ->and($license->fresh()->certificate_path)->toBeNull();

    $public = $this->getJson('/api/v1/public/verify/'.$license->verification_token)->assertOk();
    expect($public->json('data.owner_name'))->toBe('Ahmed Dealer')
        ->and($public->json('data.father_name'))->toBe('Khan')
        ->and(json_encode($public->json()))->not->toContain('1234512345123');
});

it('refuses the screen to a registration officer', function () {
    $officer = companyActor('Registration Officer');

    $this->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/licenses/previous')
        ->assertForbidden();

    $this->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/licenses/previous', [
            'licensable_type' => 'company',
            'licensable_id' => 1,
            'license_no' => 'NO-ACCESS',
            'license_kind' => 'registration',
            'dispatch_no' => 'D-14',
            'valid_to' => now()->endOfYear()->toDateString(),
        ])
        ->assertForbidden();
});
