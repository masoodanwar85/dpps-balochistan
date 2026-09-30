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
        Schema::create('company_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('person_id')->constrained('persons');
            $table->enum('role', [
                'ceo',
                'director',
                'technical_staff',
                'contact_person',
                'authorized_rep',
            ]);
            $table->string('designation', 100)->nullable();
            $table->date('appointment_date')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('end_reason', 255)->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected']);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->enum('source', ['office', 'portal', 'import']);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('active_tech_person')->nullable()->storedAs(
                "case when role = 'technical_staff' and end_date is null and deleted_at is null and verification_status <> 'rejected' then person_id end"
            );
            $table->unique('active_tech_person');
        });

        DB::statement('alter table company_people add constraint company_people_end_date_check check (end_date is null or end_date >= start_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_people');
    }
};
