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
        Schema::create('company_premises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->enum('type', [
                'head_office',
                'regional_office',
                'field_office',
                'warehouse',
            ]);
            $table->foreignId('district_id')->nullable()->constrained('districts');
            $table->text('address');
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('contact_person_id')->nullable()->constrained('persons');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->enum('asset_type', ['movable', 'immovable']);
            $table->string('description', 255);
            $table->foreignId('district_id')->nullable()->constrained('districts');
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_assets');
        Schema::dropIfExists('company_premises');
    }
};
