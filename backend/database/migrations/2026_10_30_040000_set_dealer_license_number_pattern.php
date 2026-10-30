<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'dealer_license_no_pattern')->update([
            'value' => '{DISTRICT_SERIAL:4} / DPPB - {BALOCHISTAN_SERIAL:4} / {YYYY} / {DISTRICT}{RENEWAL}',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'dealer_license_no_pattern')->update([
            'value' => 'DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}{RENEWAL}',
            'updated_at' => now(),
        ]);
    }
};
