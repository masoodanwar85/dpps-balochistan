<?php

use App\Models\ActivityLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Auth\Totp;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

function spa(): TestCase
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', 'http://localhost:5173')
        ->withHeader('Referer', 'http://localhost:5173');
}

function continueSpa(TestResponse $response): TestCase
{
    $name = (string) config('session.cookie');
    $cookie = collect($response->headers->getCookies())->first(
        fn ($cookie) => $cookie->getName() === $name
    );

    $request = spa();

    if ($cookie !== null) {
        $request = $request->withUnencryptedCookie($name, $cookie->getValue());
    }

    return $request;
}

function staff(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'password' => 'Password1',
        'must_change_password' => false,
        'is_active' => true,
    ], $overrides));
}

it('logs in with a session and returns roles and permissions', function () {
    $user = staff(['must_change_password' => true]);
    $role = Role::findOrCreate('Director', 'web');
    $permission = Permission::findOrCreate('dashboard.view', 'web');
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $login = spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $login->assertOk()
        ->assertJsonPath('data.token', null)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.must_change_password', true)
        ->assertJsonPath('data.user.roles', ['Director'])
        ->assertJsonPath('data.user.permissions', ['dashboard.view'])
        ->assertJsonPath('errors', null);

    $user->refresh();
    expect($user->failed_login_count)->toBe(0)
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();

    expect(LoginAttempt::query()->where('email', $user->email)->where('success', true)->count())->toBe(1);
    expect(ActivityLog::query()->where('action', 'login')->where('subject_id', $user->id)->count())->toBe(1);

    continueSpa($login)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.permissions', ['dashboard.view']);
});

it('records a failed login and locks the account on the fifth failure', function () {
    $user = staff();

    foreach (range(1, 4) as $attempt) {
        spa()->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.auth.0', 'These credentials do not match our records.');
    }

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(423)
        ->assertJsonPath('errors.auth.0', 'This account is locked for 15 minutes.');

    $user->refresh();
    expect($user->failed_login_count)->toBe(5)
        ->and($user->locked_until)->not->toBeNull();

    expect(LoginAttempt::query()->where('email', $user->email)->where('success', false)->count())->toBe(5);
    expect(ActivityLog::query()->where('action', 'login_failed')->where('subject_id', $user->id)->count())->toBe(5);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertStatus(423);

    $this->travel(16)->minutes();

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertOk();

    $user->refresh();
    expect($user->failed_login_count)->toBe(0)
        ->and($user->locked_until)->toBeNull();
});

it('does not write an activity log when the email is unknown', function () {
    spa()->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'Password1',
    ])->assertStatus(422);

    expect(LoginAttempt::query()->where('email', 'missing@example.com')->count())->toBe(1);
    expect(ActivityLog::query()->count())->toBe(0);
});

it('refuses an inactive account', function () {
    $user = staff(['is_active' => false]);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertForbidden()
        ->assertJsonPath('errors.auth.0', 'This account is inactive.');

    expect($user->fresh()->failed_login_count)->toBe(0);
});

it('issues a mobile token and logs out by revoking it', function () {
    $user = staff();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
        'channel' => 'mobile',
    ]);

    $login->assertOk();
    $token = $login->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $log = ActivityLog::query()->where('action', 'login')->first();
    expect($log->channel)->toBe('mobile')
        ->and($log->batch_uuid)->toHaveLength(36)
        ->and($log->ip_address)->not->toBeEmpty();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    auth()->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    expect(ActivityLog::query()->where('action', 'logout')->where('channel', 'mobile')->count())->toBe(1);
});

it('logs out a web session', function () {
    $user = staff();

    $login = spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertOk();

    continueSpa($login)->postJson('/api/v1/auth/logout')->assertOk();

    auth()->forgetGuards();

    continueSpa($login)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('requires a password change before other authenticated routes', function () {
    $user = staff(['must_change_password' => true]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/two-factor/setup')
        ->assertForbidden()
        ->assertJsonPath('errors.password.0', 'You must change your password before continuing.');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.must_change_password', true);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/password/change', [
            'current_password' => 'Password1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/password/change', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/password/change', [
            'current_password' => 'Password1',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
        ->assertOk()
        ->assertJsonPath('data.must_change_password', false);

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(ActivityLog::query()->where('action', 'updated')->where('description', 'Changed password.')->count())->toBe(1);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'NewPassword1',
    ])->assertOk();
});

it('requires a browser session to set up two-factor authentication', function () {
    $user = staff();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/two-factor/setup')
        ->assertStatus(422)
        ->assertJsonPath('errors.two_factor.0', 'Two-factor setup requires a signed-in browser session.');
});

it('turns two-factor authentication on and requires the code at login', function () {
    $user = staff();
    $login = spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertOk();

    $setup = continueSpa($login)->postJson('/api/v1/auth/two-factor/setup')->assertOk();
    $secret = $setup->json('data.secret');
    expect($secret)->toBeString()->not->toBeEmpty();
    expect($setup->json('data.otpauth_url'))->toContain('otpauth://totp/');

    $code = app(Totp::class)->currentCode($secret);

    continueSpa($setup)->postJson('/api/v1/auth/two-factor/confirm', [
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true);

    expect($user->fresh()->two_factor_secret)->not->toBe($secret);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertStatus(422)
        ->assertJsonPath('errors.code.0', 'Authentication code is required.');

    expect($user->fresh()->failed_login_count)->toBe(0);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
        'code' => '000000',
    ])->assertStatus(422);

    $login = spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
        'code' => app(Totp::class)->currentCode($secret),
    ])->assertOk()
        ->assertJsonPath('data.user.two_factor_enabled', true);

    continueSpa($login)->postJson('/api/v1/auth/two-factor/disable', [
        'password' => 'Password1',
        'code' => app(Totp::class)->currentCode($secret),
    ])->assertOk()
        ->assertJsonPath('data.two_factor_enabled', false);

    spa()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1',
    ])->assertOk();
});

it('shares one batch id for logs written during the same request', function () {
    $user = staff();
    $request = Request::create('/api/v1/auth/login', 'POST');
    $this->app->instance('request', $request);

    $logger = app(ActivityLogger::class);
    $first = $logger->log('login', 'Logged in.', 'user', $user->id, $user);
    $second = $logger->log('updated', 'Changed password.', 'user', $user->id, $user);

    expect($first->batch_uuid)->toBe($second->batch_uuid)
        ->and($first->user_type)->toBe('staff')
        ->and($first->subject_type)->toBe('user');
});

it('does not expose forgot or reset password routes', function () {
    $this->postJson('/api/v1/auth/password/forgot')->assertNotFound();
    $this->postJson('/api/v1/auth/password/reset')->assertNotFound();
});
