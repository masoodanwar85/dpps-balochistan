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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 20)->unique();
            $table->string('name', 250);
            $table->string('normalized_name', 250)->index();
            $table->enum('legal_type', [
                'private_ltd',
                'public_ltd',
                'partnership',
                'sole_proprietor',
                'other',
            ]);
            $table->string('ntn', 20)->nullable()->unique();
            $table->string('incorporation_no', 50)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->text('head_office_address');
            $table->string('city', 100);
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('landline', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 200)->nullable();
            $table->boolean('pcpa_member')->default(false);
            $table->boolean('croplife_member')->default(false);
            $table->string('membership_no', 50)->nullable();
            $table->enum('status', [
                'unlicensed',
                'active',
                'expiring',
                'expired',
                'suspended',
                'cancelled',
            ]);
            $table->string('legacy_reg_no', 100)->nullable();
            $table->text('legacy_notes')->nullable();
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
        Schema::dropIfExists('companies');
    }
};
