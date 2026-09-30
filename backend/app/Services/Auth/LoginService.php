<?php

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private TwoFactorAuthentication $twoFactor,
    ) {}

    public function attempt(string $email, string $password, ?string $code, string $channel): LoginResult
    {
        $email = mb_substr(trim($email), 0, 150);
        $code = $code !== null && trim($code) !== '' ? trim($code) : null;

        return DB::transaction(function () use ($email, $password, $code, $channel) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                $this->recordAttempt($email, false);

                return LoginResult::failure(422, [
                    'auth' => ['These credentials do not match our records.'],
                ]);
            }

            if ($user->locked_until !== null && $user->locked_until->isFuture()) {
                $this->recordAttempt($email, false);
                $this->logFailure($user, 'Failed login because the account is locked.', $channel);

                return LoginResult::failure(423, [
                    'auth' => ['This account is locked for 15 minutes.'],
                ]);
            }

            if ($user->locked_until !== null && $user->locked_until->isPast()) {
                $user->forceFill([
                    'locked_until' => null,
                    'failed_login_count' => 0,
                ])->save();
            }

            if (! Hash::check($password, $user->password)) {
                return $this->fail($user, $email, $channel, 'auth', 'These credentials do not match our records.', 'Failed login.');
            }

            if (! $user->is_active) {
                $this->recordAttempt($email, false);
                $this->logFailure($user, 'Failed login because the account is inactive.', $channel);

                return LoginResult::failure(403, [
                    'auth' => ['This account is inactive.'],
                ]);
            }

            if ($this->twoFactor->enabled($user)) {
                if ($code === null) {
                    return LoginResult::failure(422, [
                        'code' => ['Authentication code is required.'],
                    ]);
                }

                if (! $this->twoFactor->verify($user, $code)) {
                    return $this->fail($user, $email, $channel, 'code', 'The authentication code is incorrect.', 'Failed login because the authentication code was wrong.');
                }
            }

            $user->forceFill([
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
                'last_login_ip' => mb_substr((string) (request()->ip() ?: '0.0.0.0'), 0, 45),
            ])->save();

            $token = null;

            if ($channel === 'mobile') {
                $token = $user->createToken('mobile')->plainTextToken;
            } else {
                Auth::guard('web')->login($user);

                if (request()->hasSession()) {
                    request()->session()->regenerate();
                }
            }

            $this->recordAttempt($email, true);
            $this->activityLogger->log(
                'login',
                'Logged in.',
                'user',
                $user->id,
                $user,
                channel: $channel,
            );

            return LoginResult::success($user, $token);
        });
    }

    private function fail(
        User $user,
        string $email,
        string $channel,
        string $errorKey,
        string $errorMessage,
        string $description,
    ): LoginResult {
        $user->failed_login_count++;
        $locked = $user->failed_login_count >= 5;

        if ($locked) {
            $user->locked_until = now()->addMinutes(15);
        }

        $user->save();
        $this->recordAttempt($email, false);
        $this->logFailure($user, $description, $channel);

        if ($locked) {
            return LoginResult::failure(423, [
                'auth' => ['This account is locked for 15 minutes.'],
            ]);
        }

        return LoginResult::failure(422, [
            $errorKey => [$errorMessage],
        ]);
    }

    private function logFailure(User $user, string $description, string $channel): void
    {
        $this->activityLogger->log(
            'login_failed',
            $description,
            'user',
            $user->id,
            $user,
            channel: $channel,
        );
    }

    private function recordAttempt(string $email, bool $success): void
    {
        $request = request();
        $userAgent = trim((string) $request->userAgent());

        LoginAttempt::query()->create([
            'email' => $email,
            'ip_address' => mb_substr((string) ($request->ip() ?: '0.0.0.0'), 0, 45),
            'user_agent' => $userAgent !== '' ? $userAgent : 'unknown',
            'success' => $success,
            'attempted_at' => now(),
        ]);
    }
}
