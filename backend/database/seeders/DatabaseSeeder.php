<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            LookupSeeder::class,
            DistrictSeeder::class,
            WorkflowStageSeeder::class,
            RoleAndPermissionSeeder::class,
            SuperAdminSeeder::class,
            ChecklistTemplateSeeder::class,
        ]);
    }
}
