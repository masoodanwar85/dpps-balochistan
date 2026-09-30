<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\SaveUserRequest;
use App\Models\Company;
use App\Models\District;
use App\Models\User;
use App\Services\Users\UserWriter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function __construct(private UserWriter $users) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $sort = (string) $request->query('sort', 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, ['name', 'email', 'last_login_at'], true)) {
            $column = 'name';
            $direction = 'asc';
        }

        $users = User::query()
            ->with(['roles', 'company', 'districts'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->value().'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('mobile', 'like', $search);
                });
            })
            ->when($request->filled('filter.user_type'), function ($query) use ($request) {
                $query->where('user_type', $request->string('filter.user_type')->value());
            })
            ->when($request->filled('filter.is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('filter.is_active'));
            })
            ->when($request->filled('filter.role'), function ($query) use ($request) {
                $query->whereHas('roles', function ($roles) use ($request) {
                    $roles->where('name', $request->string('filter.role')->value());
                });
            })
            ->orderBy($column, $direction)
            ->paginate($perPage);

        return ApiResponse::success(
            $users->getCollection()->map(fn (User $user) => $this->payload($user))->values(),
            [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        );
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'staff_roles' => UserWriter::STAFF_ROLES,
            'company_roles' => UserWriter::COMPANY_ROLES,
            'districts' => District::query()->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'company', 'districts']);

        return ApiResponse::success($this->payload($user));
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return ApiResponse::success($this->payload($user), status: 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return ApiResponse::success($this->payload($user));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $user = $this->users->resetPassword($user, $request->string('password')->value(), $request->user());

        return ApiResponse::success($this->payload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'user_type' => $user->user_type,
            'company_id' => $user->company_id,
            'company_name' => $user->company?->name,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'districts' => $user->districts
                ->sortBy('name')
                ->map(fn (District $district) => [
                    'id' => $district->id,
                    'name' => $district->name,
                    'code' => $district->code,
                ])
                ->values()
                ->all(),
        ];
    }
}
