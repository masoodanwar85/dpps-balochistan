<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevFeeSeeder extends Seeder
{
    /**
     * Local and staging test rates only. DatabaseSeeder does not call this class.
     */
    public function run(): void
    {
        $rows = [
            ['company', 'registration', 50000],
            ['company', 'renewal', 30000],
            ['dealer', 'registration', 5000],
            ['dealer', 'renewal', 2500],
            ['company', 'late_renewal_per_day', 500],
            ['dealer', 'late_renewal_per_day', 500],
            ['company', 'no_technical_staff_per_month', 40000],
            ['company', 'restoration_per_month', 50000],
            ['dealer', 'restoration_per_month', 50000],
        ];

        foreach ($rows as [$entityType, $feeType, $amount]) {
            DB::table('fee_structures')->updateOrInsert(
                [
                    'entity_type' => $entityType,
                    'fee_type' => $feeType,
                    'effective_from' => '2020-01-01',
                ],
                [
                    'amount' => $amount,
                    'effective_to' => null,
                    'notes' => 'Development test rate.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
