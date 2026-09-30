<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreChallanRequest;
use App\Http\Requests\Applications\VerifyChallanRequest;
use App\Models\Challan;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Applications\ApplicationChallans;
use App\Services\Applications\ApplicationPresenter;
use App\Services\Documents\DocumentWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ApplicationChallanController extends Controller
{
    public function __construct(
        private ApplicationChallans $challans,
        private ApplicationPresenter $presenter,
    ) {}

    public function store(StoreChallanRequest $request, LicenseApplication $application): JsonResponse
    {
        $file = $request->file('file');

        try {
            $this->challans->store(
                $application,
                $request->validated(),
                $this->actor($request),
                $file instanceof UploadedFile ? $file : null,
            );
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($this->presenter->detail($application->refresh()), status: 201);
    }

    public function verify(VerifyChallanRequest $request, Challan $challan): JsonResponse
    {
        $this->challans->verify($challan, $request->validated(), $this->actor($request));
        $application = $challan->application;

        if (! $application instanceof LicenseApplication) {
            abort(404);
        }

        return ApiResponse::success($this->presenter->detail($application->refresh()));
    }

    private function actor(StoreChallanRequest|VerifyChallanRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
