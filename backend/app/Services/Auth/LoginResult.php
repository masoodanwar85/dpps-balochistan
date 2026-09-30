<?php

namespace App\Services\Auth;

use App\Models\User;

class LoginResult
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    private function __construct(
        public bool $successful,
        public int $status,
        public array $errors,
        public ?User $user,
        public ?string $token,
    ) {}

    public static function success(User $user, ?string $token): self
    {
        return new self(true, 200, [], $user, $token);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function failure(int $status, array $errors): self
    {
        return new self(false, $status, $errors, null, null);
    }
}
