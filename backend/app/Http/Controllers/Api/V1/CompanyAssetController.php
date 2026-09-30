<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCompanyAssetRequest;
use App\Models\Company;
use App\Models\CompanyAsset;
use App\Services\Companies\CompanyAssets;
use App\Services\Companies\CompanyPremises;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyAssetController extends Controller
{
    public function index(Company $company, CompanyAssets $assets, CompanyPremises $premises): JsonResponse
    {
        $rows = CompanyAsset::query()
            ->with('district')
            ->where('company_id', $company->id)
            ->orderBy('asset_type')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $rows->map(fn (CompanyAsset $row) => $assets->present($row))->values(),
            ['districts' => $premises->districts()],
        );
    }

    public function store(SaveCompanyAssetRequest $request, Company $company, CompanyAssets $assets): JsonResponse
    {
        $row = $assets->create($company, $request->validated(), $request->user());

        return ApiResponse::success($assets->present($row), null, 201);
    }

    public function update(SaveCompanyAssetRequest $request, CompanyAsset $companyAsset, CompanyAssets $assets): JsonResponse
    {
        $row = $assets->update($companyAsset, $request->validated(), $request->user());

        return ApiResponse::success($assets->present($row));
    }
}
