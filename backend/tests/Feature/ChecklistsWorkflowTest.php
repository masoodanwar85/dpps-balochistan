<?php

use App\Models\ActivityLog;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowStage;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WorkflowStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function checklistManager(string $role = 'Super Admin'): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole($role);

    return $user;
}

function publishedChecklist(): ChecklistTemplate
{
    $template = ChecklistTemplate::factory()->create([
        'entity_type' => 'company',
        'application_type' => 'renewal',
        'version_no' => 1,
        'name' => 'Company – Renewal',
        'status' => 'published',
        'published_at' => now(),
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'sort_order' => 1,
        'annex_code' => 'A',
        'title' => 'Application form',
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'sort_order' => 2,
        'annex_code' => 'B',
        'title' => 'Certificate of Incorporation',
    ]);

    return $template;
}

it('refuses to edit a published checklist and locks that version on an application', function () {
    $admin = checklistManager();
    $published = publishedChecklist();
    $company = Company::factory()->create();

    $this->actingAs(checklistManager('Registration Officer'), 'sanctum')
        ->getJson('/api/v1/checklist-templates')
        ->assertForbidden();

    $applicationId = DB::table('license_applications')->insertGetId(
        applicationRow($admin->id, $published->id, $company->id, 'APP-C-2026-0099', 'submitted')
    );

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/checklist-templates/'.$published->id.'/items/'.$published->items()->first()->id, [
            'annex_code' => 'A',
            'title' => 'Changed',
            'description' => null,
            'form_reference' => null,
            'is_required' => true,
            'requires_upload' => true,
            'allowed_file_types' => 'pdf,jpg,jpeg,png',
            'max_files' => 5,
            'attestation_required' => 'none',
            'requires_validity_dates' => false,
            'must_cover_license_period' => false,
            'portal_uploadable' => true,
        ])->assertUnprocessable();

    $draft = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$published->id.'/new-version')
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version_no', 2);

    $draftId = $draft->json('data.id');
    expect($draft->json('data.items'))->toHaveCount(2);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$published->id.'/new-version')
        ->assertUnprocessable();

    $added = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$draftId.'/items', [
            'annex_code' => 'c',
            'title' => 'Memorandum',
            'description' => null,
            'form_reference' => null,
            'is_required' => true,
            'requires_upload' => true,
            'allowed_file_types' => 'pdf, jpg',
            'max_files' => 1,
            'attestation_required' => 'gazetted_officer',
            'requires_validity_dates' => false,
            'must_cover_license_period' => false,
            'portal_uploadable' => true,
        ])->assertCreated();

    expect($added->json('data.annex_code'))->toBe('C')
        ->and($added->json('data.allowed_file_types'))->toBe('pdf,jpg');

    $ids = ChecklistItem::query()->where('template_id', $draftId)->orderBy('sort_order')->pluck('id')->all();
    $reordered = [$ids[2], $ids[0], $ids[1]];

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/checklist-templates/'.$draftId.'/items/reorder', ['item_ids' => $reordered])
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $ids[2]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/v1/checklist-templates/'.$draftId.'/items/'.$ids[2])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$draftId.'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect(ChecklistTemplate::query()->find($published->id)->status)->toBe('archived')
        ->and(DB::table('license_applications')->where('id', $applicationId)->value('checklist_template_id'))->toBe($published->id)
        ->and(ChecklistItem::query()->where('template_id', $published->id)->where('title', 'Application form')->exists())->toBeTrue();
});

it('copies items into a draft from another template', function () {
    $admin = checklistManager();
    $published = publishedChecklist();
    $other = ChecklistTemplate::factory()->create([
        'entity_type' => 'dealer',
        'application_type' => 'new',
        'version_no' => 1,
        'name' => 'Dealer – New',
        'status' => 'published',
    ]);
    ChecklistItem::factory()->create([
        'template_id' => $other->id,
        'annex_code' => 'Z',
        'title' => 'Dealer only item',
        'sort_order' => 1,
    ]);

    $draftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$published->id.'/new-version')
        ->json('data.id');

    $copied = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/checklist-templates/'.$draftId.'/copy-from', [
            'source_template_id' => $other->id,
        ]);

    $copied->assertOk();
    expect(collect($copied->json('data.items'))->pluck('title'))->toContain('Dealer only item');
    expect(ActivityLog::query()->where('subject_type', 'checklist_template')->where('action', 'updated')->count())->toBeGreaterThan(0);
});

it('updates workflow stages for a workflow manager and keeps the stage identity', function () {
    $this->seed(WorkflowStageSeeder::class);
    $admin = checklistManager();
    $higher = WorkflowStage::query()->where('entity_type', 'company')->where('code', 'higher_approval')->first();
    $submission = WorkflowStage::query()->where('entity_type', 'company')->where('code', 'submission')->first();

    $this->actingAs(checklistManager('District Officer'), 'sanctum')
        ->getJson('/api/v1/workflow-stages')
        ->assertForbidden();

    $listed = $this->actingAs(checklistManager('Director'), 'sanctum')
        ->getJson('/api/v1/workflow-stages?filter[entity_type]=company');

    $listed->assertOk();
    expect($listed->json('data'))->toHaveCount(7)
        ->and($listed->json('meta.permissions'))->toContain('applications.create');

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/workflow-stages', [
            'stages' => [[
                'id' => $higher->id,
                'applies_to' => 'both',
                'sla_days' => 3,
                'is_active' => true,
                'is_skippable' => false,
                'required_permission' => 'not.a.permission',
            ]],
        ])->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/workflow-stages', [
            'stages' => [[
                'id' => $submission->id,
                'applies_to' => 'both',
                'sla_days' => null,
                'is_active' => true,
                'is_skippable' => false,
                'required_permission' => 'applications.create',
            ]],
        ])->assertOk();

    $saved = $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/workflow-stages', [
            'stages' => [[
                'id' => $higher->id,
                'applies_to' => 'both',
                'sla_days' => 5,
                'is_active' => true,
                'is_skippable' => true,
                'required_permission' => 'licenses.issue',
            ]],
        ]);

    $saved->assertOk();
    $higher->refresh();
    expect($higher->sequence)->toBe(6)
        ->and($higher->code)->toBe('higher_approval')
        ->and($higher->sla_days)->toBe(5)
        ->and($higher->is_active)->toBeTrue()
        ->and($higher->is_skippable)->toBeTrue();

    expect(ActivityLog::query()->where('subject_type', 'workflow_stage')->where('subject_id', $higher->id)->count())->toBe(1);
    expect(WorkflowStage::query()->where('entity_type', 'dealer')->where('code', 'higher_approval')->value('is_active'))->toBeFalse();
});
