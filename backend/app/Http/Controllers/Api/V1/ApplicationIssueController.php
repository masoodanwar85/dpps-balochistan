<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Licenses\IssueLicenseRequest;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Applications\ApplicationPresenter;
use App\Services\Licenses\LicenseIssuer;
use App\Services\Licenses\LicenseReadiness;
use App\Services\Licenses\LicenseWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationIssueController extends Controller
{
    public function __construct(
        private LicenseReadiness $readiness,
        private LicenseIssuer $issuer,
        private ApplicationPresenter $presenter,
    ) {}

    public function preview(LicenseApplication $application): JsonResponse
    {
        $this->readiness->refresh($application);

        return ApiResponse::success($this->readiness->assessment($application->refresh()));
    }

    public function issue(IssueLicenseRequest $request, LicenseApplication $application): JsonResponse
    {
        try {
            $this->issuer->issue($application, $request->validated(), $this->actor($request));
        } catch (LicenseWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

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
