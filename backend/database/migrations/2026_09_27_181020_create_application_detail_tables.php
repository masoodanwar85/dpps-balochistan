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
        Schema::create('application_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('license_applications');
            $table->foreignId('stage_id')->constrained('workflow_stages');
            $table->dateTime('entered_at');
            $table->dateTime('due_at');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users');
            $table->enum('outcome', ['completed', 'skipped', 'returned', 'rejected'])->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
        });

        Schema::create('application_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('license_applications');
            $table->foreignId('checklist_item_id')->constrained('checklist_items');
            $table->string('title_snapshot');
            $table->string('annex_code_snapshot', 5);
            $table->enum('status', ['pending', 'submitted', 'verified', 'deficient', 'not_applicable']);
            $table->smallInteger('page_count')->nullable();
            $table->text('officer_remarks')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('license_applications');
            $table->enum('penalty_type', ['late_renewal', 'no_technical_staff', 'restoration', 'other']);
            $table->decimal('reference_rate', 12, 2)->nullable();
            $table->string('helper_info')->nullable();
            $table->string('basis');
            $table->decimal('standard_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2);
            $table->boolean('is_waived_or_reduced')->nullable()->storedAs('final_amount < standard_amount');
            $table->text('waiver_reason')->nullable();
            $table->string('waiver_order_no', 100)->nullable();
            $table->foreignId('waiver_approved_by')->nullable()->constrained('users');
            $table->foreignId('entered_by')->constrained('users');
            $table->dateTime('entered_at');
            $table->timestamps();
        });

        Schema::create('deficiency_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('license_applications');
            $table->string('letter_no', 50)->unique();
            $table->date('issued_at');
            $table->date('reply_due_date');
            $table->date('response_received_at')->nullable();
            $table->enum('status', ['open', 'resolved', 'lapsed']);
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('deficiency_letter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deficiency_letter_id')->constrained('deficiency_letters');
            $table->foreignId('application_checklist_item_id')->constrained('application_checklist_items');
            $table->text('remarks');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deficiency_letter_items');
        Schema::dropIfExists('deficiency_letters');
        Schema::dropIfExists('application_penalties');
        Schema::dropIfExists('application_checklist_items');
        Schema::dropIfExists('application_stage_logs');
    }
};
