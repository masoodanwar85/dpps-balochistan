<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_no', 100)->unique();
            $table->enum('licensable_type', ['company', 'dealer']);
            $table->unsignedBigInteger('licensable_id');
            $table->foreignId('application_id')->nullable()->constrained('license_applications');
            $table->enum('license_kind', ['registration', 'renewal', 'restoration']);
            $table->smallInteger('renewal_count')->default(0);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->enum('status', ['active', 'expired', 'suspended', 'cancelled', 'superseded']);
            $table->string('certificate_path')->nullable();
            $table->char('verification_token', 40)->unique();
            $table->enum('documents_status', ['complete', 'incomplete', 'not_applicable']);
            $table->dateTime('documents_completed_at')->nullable();
            $table->boolean('issued_with_enforcement');
            $table->boolean('is_legacy')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('alter table licenses add constraint licenses_valid_to_check check (valid_to > valid_from)');

        Schema::create('license_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses');
            $table->enum('from_status', ['active', 'expired', 'suspended', 'cancelled', 'superseded']);
            $table->enum('to_status', ['active', 'expired', 'suspended', 'cancelled', 'superseded']);
            $table->text('reason');
            $table->string('order_no', 100)->nullable();
            $table->date('effective_date');
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('license_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['company', 'dealer']);
            $table->foreignId('district_id')->nullable()->constrained('districts');
            $table->year('year');
            $table->integer('last_serial');
            $table->unique(['entity_type', 'district_id', 'year'], 'license_number_sequences_period_unique');
            $table->timestamps();
        });

        Schema::table('license_applications', function (Blueprint $table) {
            $table->foreign('previous_license_id')->references('id')->on('licenses');
        });

        Schema::table('company_products', function (Blueprint $table) {
            $table->foreign('approved_in_license_id')->references('id')->on('licenses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_products', function (Blueprint $table) {
            $table->dropForeign(['approved_in_license_id']);
        });

        Schema::table('license_applications', function (Blueprint $table) {
            $table->dropForeign(['previous_license_id']);
        });

        Schema::dropIfExists('license_number_sequences');
        Schema::dropIfExists('license_status_history');
        Schema::dropIfExists('licenses');
    }
};
