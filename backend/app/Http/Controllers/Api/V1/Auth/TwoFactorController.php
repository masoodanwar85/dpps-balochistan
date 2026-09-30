<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Services\ActivityLogger;
use App\Services\Auth\TwoFactorAuthentication;
use App\Support\ApiResponse;
use App\Support\AuthUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController
{
    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private ActivityLogger $activityLogger,
    ) {}

    public function setup(Request $request): JsonResponse
    {
        return ApiResponse::success($this->twoFactor->beginSetup($request->user()));
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->twoFactor->confirm($user, $request->string('code')->value());

        $this->activityLogger->log(
            'updated',
            'Turned on two-factor authentication.',
            'user',
            $user->id,
            $user,
        );

        return ApiResponse::success(AuthUserPayload::from($user->fresh()));
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->twoFactor->disable(
            $user,
            $request->string('password')->value(),
            $request->string('code')->value(),
        );

        $this->activityLogger->log(
            'updated',
            'Turned off two-factor authentication.',
            'user',
            $user->id,
            $user,
        );

        return ApiResponse::success(AuthUserPayload::from($user->fresh()));
    }
}
