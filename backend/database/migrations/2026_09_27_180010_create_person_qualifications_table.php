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
        Schema::create('person_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons');
            $table->foreignId('qualification_id')->constrained('qualifications');
            $table->string('institution', 200)->nullable();
            $table->year('passing_year')->nullable();
            // Foreign key to documents is added when that table exists (Step 4).
            $table->unsignedBigInteger('degree_document_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_qualifications');
    }
};
