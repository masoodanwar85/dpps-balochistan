<?php

namespace App\Services\Portal;

use App\Models\Company;
use App\Models\User;
use App\Services\Users\UserWriter;
use Illuminate\Validation\ValidationException;

class PortalUsers
{
    public function __construct(private UserWriter $users) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Company $company): array
    {
        return User::query()
            ->with('roles')
            ->where('user_type', 'company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): User
    {
        return $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'user_type' => 'company',
            'company_id' => $company->id,
            'is_active' => true,
            'roles' => [$data['role']],
            'district_ids' => [],
        ], $actor);
    }

    public function deactivate(Company $company, int $userId, User $actor): User
    {
        $user = $this->find($company, $userId);
        $roles = $user->getRoleNames()->all();

        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => ['Choose a role that matches the user type.'],
            ]);
        }

        return $this->users->update($user, [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'user_type' => 'company',
            'company_id' => $company->id,
            'is_active' => false,
            'roles' => $roles,
            'district_ids' => [],
        ], $actor);
    }

    public function resetPassword(Company $company, int $userId, string $password, User $actor): User
    {
        return $this->users->resetPassword($this->find($company, $userId), $password, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
        ];
    }

    private function find(Company $company, int $userId): User
    {
        $user = User::query()
            ->where('user_type', 'company')
            ->where('company_id', $company->id)
            ->whereKey($userId)
            ->first();

        if (! $user instanceof User) {
            abort(404);
        }

        return $user;
    }
}
