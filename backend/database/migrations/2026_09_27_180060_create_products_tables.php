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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('generic_name', 150);
            $table->string('concentration', 30);
            $table->string('formulation', 30);
            $table->enum('category', [
                'insecticide',
                'herbicide',
                'fungicide',
                'acaricide',
                'rodenticide',
                'nematicide',
                'plant_growth_regulator',
                'other',
            ]);
            $table->boolean('is_restricted')->default(false);
            $table->boolean('is_active');
            $table->unique(['generic_name', 'concentration', 'formulation']);
            $table->timestamps();
        });

        Schema::create('company_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('brand_name', 150);
            $table->string('dpp_registration_no', 100)->nullable();
            $table->date('dpp_valid_to')->nullable();
            $table->enum('source', ['own_import', 'purchase_agreement']);
            $table->boolean('sample_provided')->default(false);
            $table->enum('status', ['pending', 'approved', 'withdrawn', 'needs_mapping']);
            // Foreign key to licenses is added when that table exists (Step 4).
            $table->unsignedBigInteger('approved_in_license_id')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unique(['company_id', 'brand_name']);
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
        Schema::dropIfExists('company_products');
        Schema::dropIfExists('products');
    }
};
