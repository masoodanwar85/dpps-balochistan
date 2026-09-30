<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeficiencyLetter;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Applications\ApplicationPresenter;
use App\Services\Applications\DeficiencyLetters;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeficiencyLetterController extends Controller
{
    public function __construct(
        private DeficiencyLetters $letters,
        private ApplicationPresenter $presenter,
    ) {}

    public function store(Request $request, LicenseApplication $application): JsonResponse
    {
        $this->letters->generate($application, $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application->refresh()), status: 201);
    }

    public function resolve(Request $request, DeficiencyLetter $deficiencyLetter): JsonResponse
    {
        $letter = $this->letters->resolve($deficiencyLetter, $this->actor($request));
        $application = $letter->application;

        if (! $application instanceof LicenseApplication) {
            abort(404);
        }

        return ApiResponse::success($this->presenter->detail($application->refresh()));
    }

    public function download(Request $request, DeficiencyLetter $deficiencyLetter): JsonResponse
    {
        return ApiResponse::success($this->letters->downloadUrl($deficiencyLetter, $this->actor($request)));
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
