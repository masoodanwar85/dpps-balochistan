<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'company_license_no_pattern')->update([
            'value' => 'DPPB/{SERIAL:4}/{YYYY}{RENEWAL}',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'company_license_no_pattern')->update([
            'value' => 'DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}',
            'updated_at' => now(),
        ]);
    }
};
