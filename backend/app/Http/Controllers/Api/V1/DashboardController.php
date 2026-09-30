<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardData $dashboard) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboard->summary($this->actor($request)));
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $party = $request->string('party')->value() ?: 'company';
        $district = $request->filled('district_id') ? $request->integer('district_id') : null;

        return ApiResponse::success($this->dashboard->expiryAlerts($this->actor($request), $party, $district));
    }

    public function documentsIncomplete(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboard->documentsIncomplete($this->actor($request)));
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->value());

        if (mb_strlen($term) < 2) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($this->dashboard->search(
            $this->actor($request),
            $request->string('type')->value() ?: 'company',
            $term,
        ));
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
