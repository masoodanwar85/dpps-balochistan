<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'masoodanwar85@gmail.com'],
            [
                'name' => 'Masood Anwar',
                'mobile' => '03000000000',
                'password' => 'm@$00d6276',
                'user_type' => 'staff',
                'company_id' => null,
                'is_active' => true,
                'must_change_password' => true,
            ],
        );

        $user->syncRoles(Role::findByName('Super Admin', 'web'));
    }
}
