<?php

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('ends a web session after 30 minutes without a request', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => 'Password1',
        'must_change_password' => false,
        'is_active' => true,
    ]);

    $login = test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', 'http://localhost:5173')
        ->withHeader('Referer', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

    $login->assertOk();

    $sessionId = app('session')->driver()->getId();
    $handler = app('session')->driver()->getHandler();
    expect(config('session.lifetime'))->toBe(30)
        ->and($handler->read($sessionId))->not->toBe('');

    $this->travel(31)->minutes();

    expect($handler->read($sessionId))->toBe('');
});

it('limits repeated login attempts from one address', function () {
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(2)->by($request->ip() ?: 'unknown');
    });

    $post = fn () => test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'Password1',
        ]);

    $post()->assertStatus(422);
    $post()->assertStatus(422);
    $post()->assertStatus(429);

    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(10000)->by($request->ip() ?: 'unknown');
    });
});

it('sends HSTS only on an https response', function () {
    $this->get('https://localhost/api/v1/health')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $this->get('http://localhost/api/v1/health')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');
});
