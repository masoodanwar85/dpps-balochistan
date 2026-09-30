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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 50);
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->char('file_hash', 64)->index();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('attested_by', ['none', 'gazetted_officer', 'notary_public', 'oath_commissioner']);
            $table->smallInteger('version_no')->default(1);
            $table->foreignId('replaced_by_id')->nullable()->constrained('documents');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('uploaded_via', ['office', 'portal', 'import']);
            $table->enum('verification_status', ['pending', 'verified', 'rejected']);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('person_qualifications', function (Blueprint $table) {
            $table->foreign('degree_document_id')->references('id')->on('documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_qualifications', function (Blueprint $table) {
            $table->dropForeign(['degree_document_id']);
        });

        Schema::dropIfExists('documents');
    }
};
