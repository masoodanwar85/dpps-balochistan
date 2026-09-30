<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\AuthUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = $request->string('password')->value();
        $user->must_change_password = false;
        $user->save();

        $this->activityLogger->log(
            'updated',
            'Changed password.',
            'user',
            $user->id,
            $user,
        );

        return ApiResponse::success(AuthUserPayload::from($user->fresh()));
    }
}
