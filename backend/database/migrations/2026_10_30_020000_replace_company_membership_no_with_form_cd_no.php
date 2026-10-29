<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('form_cd_no', 50)->nullable()->after('incorporation_date');
            $table->dropColumn('membership_no');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('membership_no', 50)->nullable()->after('croplife_member');
            $table->dropColumn('form_cd_no');
        });
    }
};
