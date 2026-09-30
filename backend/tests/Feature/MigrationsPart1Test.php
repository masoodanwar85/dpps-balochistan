<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the users access settings fees and lookup tables', function () {
    $tables = [
        'users',
        'user_districts',
        'permissions',
        'roles',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'personal_access_tokens',
        'login_attempts',
        'settings',
        'fee_structures',
        'provinces',
        'districts',
        'tehsils',
        'qualifications',
        'document_types',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('users', [
        'name',
        'email',
        'mobile',
        'password',
        'user_type',
        'company_id',
        'is_active',
        'must_change_password',
        'two_factor_secret',
        'failed_login_count',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumn('users', 'created_by'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'remember_token'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'company_id'))->toBeTrue();
});

it('stores a staff user and the related lookup rows', function () {
    $user = User::factory()->create();

    expect($user->user_type)->toBe('staff')
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->company_id)->toBeNull();

    $provinceId = DB::table('provinces')->insertGetId([
        'name' => 'Balochistan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $districtId = DB::table('districts')->insertGetId([
        'name' => 'Quetta',
        'code' => 'QTA',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('tehsils')->insert([
        'district_id' => $districtId,
        'name' => 'Quetta City',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('user_districts')->insert([
        'user_id' => $user->id,
        'district_id' => $districtId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('qualifications')->insert([
        'name' => 'BSc Agriculture',
        'is_agriculture_degree' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('document_types')->insert([
        'name' => 'CNIC',
        'category' => 'cnic',
        'applies_to' => 'person',
        'has_expiry' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('settings')->insert([
        'key' => 'directorate_name',
        'value' => 'Directorate of Plant Protection',
        'data_type' => 'string',
        'group' => 'general',
        'label' => 'Directorate name',
        'description' => 'Name printed on certificates.',
        'is_ui_editable' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fee_structures')->insert([
        'entity_type' => 'company',
        'fee_type' => 'registration',
        'amount' => 50000,
        'effective_from' => '2020-01-01',
        'effective_to' => null,
        'notes' => 'Reference rate only.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('login_attempts')->insert([
        'email' => $user->email,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'success' => false,
        'attempted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('provinces')->where('id', $provinceId)->exists())->toBeTrue()
        ->and(DB::table('user_districts')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('fee_structures')->count())->toBe(1);

    expect(fn () => DB::table('user_districts')->insert([
        'user_id' => $user->id,
        'district_id' => $districtId,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
