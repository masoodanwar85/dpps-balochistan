<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCsrRndFileRequest;
use App\Models\Company;
use App\Models\CompanyCsrRndFile;
use App\Services\Companies\CsrRndMediaStore;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyCsrRndFileController extends Controller
{
    public function index(Request $request, Company $company, CsrRndMediaStore $store): JsonResponse
    {
        abort_unless($request->user()?->can('companies.view') ?? false, 403);

        return ApiResponse::success(
            $store->list($company, $request->query('kind')),
            [
                'kinds' => $store->kindsFor($company),
                'csr' => (bool) $company->csr,
                'rnd' => (bool) $company->rnd,
            ],
        );
    }

    public function store(SaveCsrRndFileRequest $request, Company $company, CsrRndMediaStore $store): JsonResponse
    {
        $file = $store->create($company, $request->file('file'), $request->validated(), $request->user());

        return ApiResponse::success($store->present($file), status: 201);
    }

    public function downloadUrl(Request $request, CompanyCsrRndFile $companyCsrRndFile, CsrRndMediaStore $store): JsonResponse
    {
        abort_unless($request->user()?->can('documents.download') ?? false, 403);
        $store->assertAccessible($companyCsrRndFile, $request->user());

        return ApiResponse::success($store->downloadUrl($companyCsrRndFile, $request->user()));
    }
}
