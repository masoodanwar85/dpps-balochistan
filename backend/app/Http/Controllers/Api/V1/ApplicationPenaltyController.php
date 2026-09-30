<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StorePenaltyRequest;
use App\Models\ApplicationPenalty;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Applications\ApplicationPenalties;
use App\Services\Applications\ApplicationPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationPenaltyController extends Controller
{
    public function __construct(
        private ApplicationPenalties $penalties,
        private ApplicationPresenter $presenter,
    ) {}

    public function store(StorePenaltyRequest $request, LicenseApplication $application): JsonResponse
    {
        $this->penalties->store($application, $request->validated(), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application->refresh()), status: 201);
    }

    public function approve(Request $request, LicenseApplication $application, ApplicationPenalty $applicationPenalty): JsonResponse
    {
        $this->penalties->approve($application, $applicationPenalty, $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application->refresh()));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
