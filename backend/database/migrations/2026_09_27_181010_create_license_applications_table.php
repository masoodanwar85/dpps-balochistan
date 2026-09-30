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
        Schema::create('license_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 30)->unique();
            $table->enum('licensable_type', ['company', 'dealer']);
            $table->unsignedBigInteger('licensable_id');
            $table->enum('application_type', ['new', 'renewal', 'restoration']);
            $table->foreignId('checklist_template_id')->constrained('checklist_templates');
            // Foreign key to licenses is added when that table exists.
            $table->unsignedBigInteger('previous_license_id')->nullable();
            $table->enum('submitted_via', ['office', 'portal']);
            $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->dateTime('submitted_at')->nullable();
            $table->string('diary_no', 50)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->dateTime('received_at')->nullable();
            $table->smallInteger('total_pages')->nullable();
            $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages');
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'deficiency_issued',
                'fee_pending',
                'ready_to_issue',
                'issued',
                'rejected',
                'withdrawn',
            ]);
            $table->integer('late_days')->default(0);
            $table->decimal('fee_amount', 12, 2);
            $table->decimal('penalty_total', 12, 2);
            $table->decimal('total_payable', 12, 2);
            $table->text('rejection_reason')->nullable();
            $table->tinyInteger('open_flag')->nullable()->storedAs(
                "case when status not in ('issued', 'rejected', 'withdrawn') then 1 end"
            );
            $table->unique(['licensable_type', 'licensable_id', 'open_flag'], 'license_applications_one_open');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_applications');
    }
};
