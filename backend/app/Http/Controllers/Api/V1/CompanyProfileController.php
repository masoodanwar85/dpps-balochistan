<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Services\Companies\CompanyActivity;
use App\Services\Companies\CompanyProfile;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function show(Company $company, CompanyProfile $profile): JsonResponse
    {
        return ApiResponse::success($profile->present($company));
    }

    public function activity(Request $request, Company $company, CompanyActivity $activity): JsonResponse
    {
        $page = ListQuery::paginate(
            $request,
            $activity->query($company),
            ['created_at', 'action'],
            '-created_at',
        );

        return ApiResponse::success(
            $page->getCollection()->map(fn (ActivityLog $log) => $activity->present($log))->values(),
            ListQuery::meta($page),
        );
    }
}
