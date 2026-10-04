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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('csr')->default(false)->after('membership_no');
            $table->boolean('rnd')->default(false)->after('csr');
        });

        Schema::create('company_csr_rnd_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->enum('kind', ['csr', 'rnd']);
            $table->string('title', 255);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->char('file_hash', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('uploaded_via', ['office', 'portal']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_csr_rnd_files');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['csr', 'rnd']);
        });
    }
};
