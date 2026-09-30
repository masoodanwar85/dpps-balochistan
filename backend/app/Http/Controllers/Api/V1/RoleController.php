<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->payload($role));

        return ApiResponse::success($roles);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $permissions = $request->validated('permissions');

        if ($role->name === 'Super Admin' && (! in_array('users.manage', $permissions, true) || ! in_array('roles.manage', $permissions, true))) {
            throw ValidationException::withMessages([
                'permissions' => ['The Super Admin role keeps user and role management.'],
            ]);
        }

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($permissions);
        $role->load('permissions');
        $after = $role->permissions->pluck('name')->sort()->values()->all();

        $this->activityLogger->log(
            'updated',
            'Updated role permissions.',
            'role',
            $role->id,
            $request->user(),
            oldValues: ['permissions' => $before],
            newValues: ['permissions' => $after],
        );

        return ApiResponse::success($this->payload($role));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
