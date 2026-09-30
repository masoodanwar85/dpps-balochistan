<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthentication
{
    public function __construct(private Totp $totp) {}

    public function enabled(User $user): bool
    {
        return $this->secret($user) !== null;
    }

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function beginSetup(User $user): array
    {
        if (! request()->hasSession()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor setup requires a signed-in browser session.'],
            ]);
        }

        if ($this->enabled($user)) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already turned on.'],
            ]);
        }

        $secret = $this->totp->generateSecret();
        session(['two_factor_pending_secret' => $secret]);

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->provisioningUri($user->email, $secret),
        ];
    }

    public function confirm(User $user, string $code): void
    {
        $secret = session('two_factor_pending_secret');

        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'two_factor' => ['Set up two-factor authentication before confirming it.'],
            ]);
        }

        if (! $this->totp->verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The authentication code is incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
        ])->save();

        session()->forget('two_factor_pending_secret');
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->secret($user);

        return $secret !== null && $this->totp->verify($secret, $code);
    }

    public function disable(User $user, string $password, string $code): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if (! $this->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The authentication code is incorrect.'],
            ]);
        }

        $user->forceFill(['two_factor_secret' => null])->save();
    }

    private function secret(User $user): ?string
    {
        if ($user->two_factor_secret === null || $user->two_factor_secret === '') {
            return null;
        }

        try {
            return Crypt::decryptString($user->two_factor_secret);
        } catch (DecryptException) {
            return null;
        }
    }
}
