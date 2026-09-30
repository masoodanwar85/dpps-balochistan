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
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 10);
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('tehsils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts');
            $table->string('name', 150);
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('is_agriculture_degree');
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('category', [
                'application',
                'cnic',
                'license',
                'challan',
                'inspection',
                'correspondence',
                'agreement',
                'qualification',
                'other',
            ]);
            $table->enum('applies_to', ['company', 'dealer', 'person', 'any']);
            $table->boolean('has_expiry');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('qualifications');
        Schema::dropIfExists('tehsils');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
