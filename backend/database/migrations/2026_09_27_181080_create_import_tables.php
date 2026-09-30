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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('sheet_name', 100);
            $table->foreignId('imported_by')->constrained('users');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->integer('total_rows');
            $table->integer('success_rows');
            $table->integer('exception_rows');
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->timestamps();
        });

        Schema::create('import_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches');
            $table->integer('row_no');
            $table->json('raw_data');
            $table->enum('issue_type', [
                'bad_date',
                'possible_duplicate',
                'missing_cnic',
                'invalid_cnic',
                'unknown_district',
                'unparsed_product',
                'missing_required',
                'other',
            ]);
            $table->string('issue_details');
            $table->enum('resolution_status', ['open', 'fixed', 'merged', 'skipped']);
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_exceptions');
        Schema::dropIfExists('import_batches');
    }
};
