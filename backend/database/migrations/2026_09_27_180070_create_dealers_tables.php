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
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('dealer_code', 20)->unique();
            $table->string('shop_name', 250);
            $table->string('normalized_name', 250)->index();
            $table->foreignId('district_id')->constrained('districts');
            $table->foreignId('tehsil_id')->nullable()->constrained('tehsils');
            $table->text('business_address');
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 150)->nullable();
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

        Schema::create('dealer_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers');
            $table->foreignId('person_id')->constrained('persons');
            $table->date('start_date');
            $table->date('end_date')->nullable();
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
        Schema::dropIfExists('dealer_owners');
        Schema::dropIfExists('dealers');
    }
};
