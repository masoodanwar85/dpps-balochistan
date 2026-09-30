<?php

use App\Models\Company;
use App\Models\Dealer;
use App\Models\District;
use App\Models\License;
use App\Models\User;
use App\Services\Statuses\StatusRefresh;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function statusLicense(string $type, int $id, int $userId, string $validTo, string $status = 'active'): License
{
    return License::query()->create([
        'license_no' => 'DPP-'.Str::upper(Str::random(8)),
        'licensable_type' => $type,
        'licensable_id' => $id,
        'license_kind' => 'registration',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => $validTo,
        'issued_at' => now()->subYear(),
        'status' => $status,
        'verification_token' => Str::random(40),
        'documents_status' => 'incomplete',
        'issued_with_enforcement' => false,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

it('expires a past license and marks the company expired, expiring, or active', function () {
    $director = companyActor('Director');
    $past = Company::factory()->create(['status' => 'active']);
    $soon = Company::factory()->create(['status' => 'active']);
    $later = Company::factory()->create(['status' => 'active']);
    $none = Company::factory()->create(['status' => 'active']);
    $held = Company::factory()->create(['status' => 'suspended']);
    statusLicense('company', $past->id, $director->id, now()->subDay()->toDateString());
    statusLicense('company', $soon->id, $director->id, now()->addDays(10)->toDateString());
    statusLicense('company', $later->id, $director->id, now()->addDays(120)->toDateString());
    statusLicense('company', $held->id, $director->id, now()->subDay()->toDateString(), 'suspended');

    app(StatusRefresh::class)->run();

    expect($past->fresh()->status)->toBe('expired')
        ->and(License::query()->where('licensable_id', $past->id)->value('status'))->toBe('expired')
        ->and($soon->fresh()->status)->toBe('expiring')
        ->and(License::query()->where('licensable_id', $soon->id)->value('status'))->toBe('active')
        ->and($later->fresh()->status)->toBe('active')
        ->and($none->fresh()->status)->toBe('unlicensed')
        ->and($held->fresh()->status)->toBe('suspended')
        ->and(License::query()->where('licensable_id', $held->id)->value('status'))->toBe('suspended');
});

it('keeps a cancelled dealer cancelled and limits dealer counts to a district officer', function () {
    $director = companyActor('Director');
    $officer = companyActor('District Officer');
    $quetta = District::factory()->create();
    $pishin = District::factory()->create();
    $officer->districts()->attach($quetta->id);
    $cancelled = Dealer::factory()->create([
        'district_id' => $quetta->id,
        'status' => 'cancelled',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    $local = Dealer::factory()->create([
        'district_id' => $quetta->id,
        'status' => 'active',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    $other = Dealer::factory()->create([
        'district_id' => $pishin->id,
        'status' => 'active',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    Company::factory()->create(['status' => 'active']);
    statusLicense('dealer', $cancelled->id, $director->id, now()->subDay()->toDateString());
    statusLicense('dealer', $local->id, $director->id, now()->addDays(5)->toDateString());
    statusLicense('dealer', $other->id, $director->id, now()->addYear()->toDateString());

    app(StatusRefresh::class)->run();

    expect($cancelled->fresh()->status)->toBe('cancelled')
        ->and($local->fresh()->status)->toBe('expiring');

    $summary = $this->actingAs($officer, 'sanctum')->getJson('/api/v1/dashboard/summary')->assertOk();
    expect($summary->json('data.companies.total'))->toBe(1)
        ->and($summary->json('data.dealers.total'))->toBe(2)
        ->and($summary->json('data.dealers.expiring'))->toBe(1);

    $alerts = $this->actingAs($officer, 'sanctum')->getJson('/api/v1/dashboard/expiry-alerts?party=dealer')->assertOk();
    expect(collect($alerts->json('data'))->pluck('licensable_id'))->toContain($local->id)
        ->and(collect($alerts->json('data'))->pluck('licensable_id'))->not->toContain($other->id);
});

it('notifies a company user once when the renewal window is open', function () {
    $director = companyActor('Director');
    $company = Company::factory()->create(['status' => 'active']);
    $user = User::factory()->create([
        'user_type' => 'company',
        'company_id' => $company->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    statusLicense('company', $company->id, $director->id, now()->addDays(20)->toDateString());

    app(StatusRefresh::class)->run();
    app(StatusRefresh::class)->run();

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['kind'])->toBe('renewal_window');

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('meta.unread', 1);
});
