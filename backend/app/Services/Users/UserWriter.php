<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserWriter
{
    public const STAFF_ROLES = [
        'Super Admin',
        'Director',
        'Registration Officer',
        'District Officer',
        'Data Entry Operator',
        'Auditor',
    ];

    public const COMPANY_ROLES = [
        'Company Admin',
        'Company Staff',
    ];

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $this->assertAssignment($data);

        return DB::transaction(function () use ($data, $actor) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => $data['password'],
                'user_type' => $data['user_type'],
                'company_id' => $data['user_type'] === 'company' ? $data['company_id'] : null,
                'is_active' => $data['is_active'],
                'must_change_password' => true,
            ]);

            $this->syncAccess($user, $data);

            $this->activityLogger->log(
                'created',
                'Created user.',
                'user',
                $user->id,
                $actor,
                newValues: $this->snapshot($user),
            );

            return $user->fresh(['roles', 'company', 'districts']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $this->assertAssignment($data, $user, $actor);

        return DB::transaction(function () use ($user, $data, $actor) {
            $before = $this->snapshot($user);

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'user_type' => $data['user_type'],
                'company_id' => $data['user_type'] === 'company' ? $data['company_id'] : null,
                'is_active' => $data['is_active'],
            ])->save();

            $this->syncAccess($user, $data);

            $this->activityLogger->log(
                'updated',
                'Updated user.',
                'user',
                $user->id,
                $actor,
                oldValues: $before,
                newValues: $this->snapshot($user),
            );

            return $user->fresh(['roles', 'company', 'districts']);
        });
    }

    public function resetPassword(User $user, string $password, User $actor): User
    {
        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $this->activityLogger->log(
            'updated',
            'Reset password.',
            'user',
            $user->id,
            $actor,
        );

        return $user->fresh(['roles', 'company', 'districts']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAssignment(array $data, ?User $user = null, ?User $actor = null): void
    {
        $roles = $data['roles'];
        $allowed = $data['user_type'] === 'company' ? self::COMPANY_ROLES : self::STAFF_ROLES;
        $unknown = array_values(array_diff($roles, $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'roles' => ['Choose a role that matches the user type.'],
            ]);
        }

        if ($data['user_type'] === 'company' && empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => ['A company user must be linked to a company.'],
            ]);
        }

        if ($data['user_type'] === 'staff' && ! empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => ['A staff user is not linked to a company.'],
            ]);
        }

        if (in_array('District Officer', $roles, true) && $data['district_ids'] === []) {
            throw ValidationException::withMessages([
                'district_ids' => ['A District Officer needs at least one district.'],
            ]);
        }

        if ($user !== null && $actor !== null && $user->is($actor) && $data['is_active'] === false) {
            throw ValidationException::withMessages([
                'is_active' => ['You cannot deactivate your own account.'],
            ]);
        }

        if ($user !== null && $actor !== null && $user->is($actor) && $user->hasRole('Super Admin') && ! in_array('Super Admin', $roles, true)) {
            throw ValidationException::withMessages([
                'roles' => ['You cannot remove your own Super Admin role.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncAccess(User $user, array $data): void
    {
        $user->syncRoles($data['roles']);

        $districtIds = in_array('District Officer', $data['roles'], true) ? $data['district_ids'] : [];
        $user->districts()->sync($districtIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $user->loadMissing(['roles', 'districts']);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'user_type' => $user->user_type,
            'company_id' => $user->company_id,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'district_ids' => $user->districts->pluck('id')->sort()->values()->all(),
        ];
    }
}
