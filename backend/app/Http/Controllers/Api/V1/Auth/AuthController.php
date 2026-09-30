<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use App\Support\AuthUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AuthController
{
    public function __construct(
        private LoginService $loginService,
        private ActivityLogger $activityLogger,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->attempt(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->filled('code') ? $request->string('code')->value() : null,
            $request->string('channel')->value() ?: 'web',
        );

        if (! $result->successful || $result->user === null) {
            return ApiResponse::error($result->errors, $result->status);
        }

        return ApiResponse::success([
            'user' => AuthUserPayload::from($result->user),
            'token' => $result->token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(AuthUserPayload::from($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $channel = $token instanceof PersonalAccessToken ? 'mobile' : 'web';

        $this->activityLogger->log(
            'logout',
            'Logged out.',
            'user',
            $user->id,
            $user,
            channel: $channel,
        );

        if ($token && ! $token instanceof TransientToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success();
    }
}
