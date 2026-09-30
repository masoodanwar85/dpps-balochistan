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
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('license_applications');
            $table->string('challan_no', 50)->unique();
            $table->string('bank_name', 100);
            $table->string('branch', 100)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->enum('verification_status', ['pending', 'verified', 'rejected']);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challan_id')->constrained('challans');
            $table->enum('item_type', [
                'registration_fee',
                'renewal_fee',
                'late_renewal_penalty',
                'no_technical_staff_penalty',
                'restoration_penalty',
                'other',
            ]);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challan_items');
        Schema::dropIfExists('challans');
    }
};
