<?php

namespace App\Support;

use App\Models\User;

class AuthUserPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'user_type' => $user->user_type,
            'company_id' => $user->company_id,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'two_factor_enabled' => $user->two_factor_secret !== null && $user->two_factor_secret !== '',
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->unique()->sort()->values()->all(),
        ];
    }
}
