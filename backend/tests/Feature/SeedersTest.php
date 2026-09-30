<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\District;
use App\Models\DocumentType;
use App\Models\Province;
use App\Models\Qualification;
use App\Models\Setting;
use App\Models\Tehsil;
use App\Models\User;
use App\Models\WorkflowStage;
use Database\Seeders\DevFeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds the phase 1 reference data and leaves fees empty', function () {
    $this->seed();

    expect(Setting::query()->count())->toBe(13)
        ->and(Setting::query()->where('key', 'enforce_document_requirements')->value('value'))->toBe('false')
        ->and(Setting::query()->where('key', 'public_verify_base_url')->value('is_ui_editable'))->toBeFalse()
        ->and(Province::query()->count())->toBe(7)
        ->and(Qualification::query()->count())->toBe(12)
        ->and(DocumentType::query()->count())->toBe(23)
        ->and(DB::table('fee_structures')->count())->toBe(0);

    expect(WorkflowStage::query()->count())->toBe(14)
        ->and(WorkflowStage::query()->where('code', 'higher_approval')->where('is_active', true)->count())->toBe(0)
        ->and(WorkflowStage::query()->where('code', 'submission')->whereNull('sla_days')->count())->toBe(2)
        ->and(WorkflowStage::query()->where('code', 'deficiency')->where('is_skippable', true)->count())->toBe(2);

    expect(ChecklistTemplate::query()->where('status', 'published')->count())->toBe(4)
        ->and(ChecklistItem::query()->whereHas('template', fn ($query) => $query->where('application_type', 'new'))->count())->toBe(36)
        ->and(ChecklistItem::query()->whereHas('template', fn ($query) => $query->where('application_type', 'renewal'))->count())->toBe(54);

    $newCompany = ChecklistTemplate::query()->where('entity_type', 'company')->where('application_type', 'new')->first();

    expect($newCompany->items()->where('annex_code', 'V')->value('requires_upload'))->toBeFalse()
        ->and($newCompany->items()->where('annex_code', 'S')->value('requires_validity_dates'))->toBeTrue()
        ->and($newCompany->items()->where('annex_code', 'S')->value('must_cover_license_period'))->toBeTrue()
        ->and($newCompany->items()->where('annex_code', 'X')->value('is_required'))->toBeFalse()
        ->and($newCompany->items()->where('annex_code', 'I')->exists())->toBeFalse();

    expect(District::query()->whereRaw('lower(name) = ?', ['quetta'])->value('code'))->toBe('QTA')
        ->and(District::query()->where('name', 'Barkhan')->value('code'))->toBe('BRK')
        ->and(District::query()->where('name', 'Khuzdar')->value('code'))->toBe('KZD')
        ->and(District::query()->where('name', 'Hub')->value('code'))->toBe('HUB')
        ->and(District::query()->whereRaw('lower(name) = ?', ['jaffarabad'])->count())->toBe(1)
        ->and(Tehsil::query()->where('name', '0')->exists())->toBeFalse();

    $awaran = District::query()->where('name', 'Awaran')->first();

    expect($awaran->tehsils()->pluck('name')->sort()->values()->all())
        ->toBe(['Awaran', 'Jhal Jhao', 'Mashkai']);

    $admin = User::query()->where('email', 'masoodanwar85@gmail.com')->first();

    expect($admin->name)->toBe('Masood Anwar')
        ->and($admin->must_change_password)->toBeTrue()
        ->and($admin->user_type)->toBe('staff')
        ->and(Hash::check('m@$00d6276', $admin->password))->toBeTrue()
        ->and($admin->hasRole('Super Admin'))->toBeTrue();

    expect(Role::query()->count())->toBe(8)
        ->and(Role::findByName('Data Entry Operator')->hasPermissionTo('applications.create'))->toBeTrue()
        ->and(Role::findByName('Data Entry Operator')->hasPermissionTo('applications.process'))->toBeFalse()
        ->and(Role::findByName('Company Staff')->hasPermissionTo('licenses.issue'))->toBeFalse()
        ->and(Role::findByName('Director')->hasPermissionTo('penalties.waive'))->toBeTrue()
        ->and(Role::findByName('Registration Officer')->hasPermissionTo('licenses.issue'))->toBeFalse();
});

it('keeps development fee rates out of the main seeder', function () {
    $this->seed(DevFeeSeeder::class);

    expect(DB::table('fee_structures')->count())->toBe(9)
        ->and(DB::table('fee_structures')->where('entity_type', 'dealer')->where('fee_type', 'no_technical_staff_per_month')->exists())->toBeFalse()
        ->and(DB::table('fee_structures')->where('entity_type', 'company')->where('fee_type', 'registration')->value('amount'))->toEqual(50000);
});

it('builds a record from each seeded model factory', function () {
    expect(Setting::factory()->create()->exists)->toBeTrue()
        ->and(Province::factory()->create()->exists)->toBeTrue()
        ->and(District::factory()->create()->exists)->toBeTrue()
        ->and(Tehsil::factory()->create()->exists)->toBeTrue()
        ->and(Qualification::factory()->create()->exists)->toBeTrue()
        ->and(DocumentType::factory()->create()->exists)->toBeTrue()
        ->and(WorkflowStage::factory()->create()->exists)->toBeTrue()
        ->and(ChecklistTemplate::factory()->create()->exists)->toBeTrue()
        ->and(ChecklistItem::factory()->create()->exists)->toBeTrue();
});
