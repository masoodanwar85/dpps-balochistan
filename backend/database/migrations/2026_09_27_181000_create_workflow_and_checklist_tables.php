<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['company', 'dealer']);
            $table->tinyInteger('sequence');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description');
            $table->enum('applies_to', ['new', 'renewal', 'both']);
            $table->smallInteger('sla_days');
            $table->boolean('is_active');
            $table->boolean('is_skippable');
            $table->string('required_permission', 100);
            $table->unique(['entity_type', 'sequence']);
            $table->timestamps();
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['company', 'dealer']);
            $table->enum('application_type', ['new', 'renewal']);
            $table->smallInteger('version_no');
            $table->string('name', 150);
            $table->enum('status', ['draft', 'published', 'archived']);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->unique(['entity_type', 'application_type', 'version_no'], 'checklist_templates_version_unique');
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates');
            $table->smallInteger('sort_order');
            $table->string('annex_code', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('form_reference', 30)->nullable();
            $table->boolean('is_required');
            $table->boolean('requires_upload');
            $table->string('allowed_file_types', 100);
            $table->tinyInteger('max_files')->default(1);
            $table->enum('attestation_required', ['none', 'gazetted_officer', 'notary_public', 'oath_commissioner']);
            $table->boolean('requires_validity_dates');
            $table->boolean('must_cover_license_period');
            $table->boolean('portal_uploadable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('workflow_stages');
    }
};
