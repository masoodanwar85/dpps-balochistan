<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\District;
use App\Models\User;
use App\Support\PermissionMatrix;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function adminUser(): User
{
    $user = User::factory()->create([
        'password' => 'Password1',
        'must_change_password' => false,
    ]);
    $user->assignRole('Super Admin');

    return $user;
}

it('lists and creates a district officer only for a user manager', function () {
    $admin = adminUser();
    $director = User::factory()->create(['must_change_password' => false]);
    $director->assignRole('Director');
    $district = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);

    $this->actingAs($director, 'sanctum')->getJson('/api/v1/users')->assertForbidden();

    $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'District Officer',
        'email' => 'officer@example.com',
        'mobile' => '03001112233',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_type' => 'staff',
        'company_id' => null,
        'is_active' => true,
        'roles' => ['District Officer'],
        'district_ids' => [$district->id],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.roles', ['District Officer'])
        ->assertJsonPath('data.districts.0.code', 'QTA')
        ->assertJsonPath('data.must_change_password', true);

    $officer = User::query()->where('email', 'officer@example.com')->first();
    expect($officer->districts)->toHaveCount(1)
        ->and(Hash::check('Password1', $officer->password))->toBeTrue();

    $log = ActivityLog::query()->where('action', 'created')->where('subject_id', $officer->id)->first();
    expect($log->new_values)->not->toHaveKey('password');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/users?search=officer@example.com&filter[role]=District Officer')
        ->assertOk()
        ->assertJsonPath('data.0.email', 'officer@example.com')
        ->assertJsonPath('meta.total', 1);
});

it('keeps each district officer inside the districts assigned to that user', function () {
    $admin = adminUser();
    $quetta = District::factory()->create(['name' => 'Quetta', 'code' => 'QTA']);
    $hub = District::factory()->create(['name' => 'Hub', 'code' => 'HUB']);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', officerPayload('one@example.com', [$quetta->id]))->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', officerPayload('two@example.com', [$hub->id]))->assertCreated();

    $one = User::query()->where('email', 'one@example.com')->first();
    $two = User::query()->where('email', 'two@example.com')->first();
    $one->forceFill(['must_change_password' => false])->save();
    $two->forceFill(['must_change_password' => false])->save();

    expect($one->districts->pluck('id')->all())->toBe([$quetta->id])
        ->and($two->districts->pluck('id')->all())->toBe([$hub->id]);

    $this->actingAs($one, 'sanctum')->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($two, 'sanctum')->getJson('/api/v1/users/'.$one->id)->assertForbidden();
});

it('links a company user to one company and refuses a staff company link', function () {
    $admin = adminUser();
    $company = Company::factory()->create(['name' => 'Rudolf Life Sciences']);
    $companyAdmin = User::factory()->create([
        'user_type' => 'company',
        'company_id' => $company->id,
        'must_change_password' => false,
    ]);
    $companyAdmin->assignRole('Company Admin');

    $this->actingAs($companyAdmin, 'sanctum')->getJson('/api/v1/users')->assertForbidden();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'Portal Staff',
        'email' => 'staff@example.com',
        'mobile' => '03002223344',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_type' => 'company',
        'company_id' => $company->id,
        'is_active' => true,
        'roles' => ['Company Staff'],
        'district_ids' => [],
    ])->assertCreated()
        ->assertJsonPath('data.company_name', 'Rudolf Life Sciences');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'Bad Staff',
        'email' => 'bad-staff@example.com',
        'mobile' => '03003334455',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_type' => 'staff',
        'company_id' => $company->id,
        'is_active' => true,
        'roles' => ['Data Entry Operator'],
        'district_ids' => [],
    ])->assertStatus(422)->assertJsonPath('errors.company_id.0', 'A staff user is not linked to a company.');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'Bad Company',
        'email' => 'bad-company@example.com',
        'mobile' => '03004445566',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_type' => 'company',
        'company_id' => $company->id,
        'is_active' => true,
        'roles' => ['Director'],
        'district_ids' => [],
    ])->assertStatus(422);
});

it('resets a password to a temporary one and requires a change', function () {
    $admin = adminUser();
    $user = User::factory()->create([
        'password' => 'Password1',
        'must_change_password' => false,
        'failed_login_count' => 5,
        'locked_until' => now()->addMinutes(10),
    ]);
    $user->assignRole('Auditor');

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users/'.$user->id.'/reset-password', [
        'password' => 'TempPass1',
        'password_confirmation' => 'TempPass1',
    ])->assertOk()->assertJsonPath('data.must_change_password', true);

    $user->refresh();
    expect(Hash::check('TempPass1', $user->password))->toBeTrue()
        ->and($user->failed_login_count)->toBe(0)
        ->and($user->locked_until)->toBeNull();

    expect(ActivityLog::query()->where('description', 'Reset password.')->where('subject_id', $user->id)->count())->toBe(1);
});

it('saves the role matrix and blocks a user without roles.manage', function () {
    $admin = adminUser();
    $director = User::factory()->create(['must_change_password' => false]);
    $director->assignRole('Director');
    $auditor = Role::findByName('Auditor', 'web');

    $this->actingAs($director, 'sanctum')->putJson('/api/v1/roles/'.$auditor->id, [
        'permissions' => ['dashboard.view'],
    ])->assertForbidden();

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/roles/'.$auditor->id, [
        'permissions' => ['dashboard.view', 'companies.view'],
    ])->assertOk()
        ->assertJsonPath('data.permissions', ['companies.view', 'dashboard.view']);

    expect($auditor->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['companies.view', 'dashboard.view']);

    $superAdmin = Role::findByName('Super Admin', 'web');
    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/roles/'.$superAdmin->id, [
        'permissions' => ['dashboard.view'],
    ])->assertStatus(422);
});

it('matches every seeded permission in the role matrix', function () {
    $seeded = Permission::query()->pluck('name')->sort()->values()->all();
    $matrix = collect(PermissionMatrix::keys())->sort()->values()->all();

    expect($matrix)->toBe($seeded);
});

it('refuses to deactivate the signed-in user', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'sanctum')->putJson('/api/v1/users/'.$admin->id, [
        'name' => $admin->name,
        'email' => $admin->email,
        'mobile' => $admin->mobile,
        'user_type' => 'staff',
        'company_id' => null,
        'is_active' => false,
        'roles' => ['Super Admin'],
        'district_ids' => [],
    ])->assertStatus(422)->assertJsonPath('errors.is_active.0', 'You cannot deactivate your own account.');
});

function officerPayload(string $email, array $districtIds): array
{
    return [
        'name' => 'Officer '.$email,
        'email' => $email,
        'mobile' => '03005556677',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_type' => 'staff',
        'company_id' => null,
        'is_active' => true,
        'roles' => ['District Officer'],
        'district_ids' => $districtIds,
    ];
}
