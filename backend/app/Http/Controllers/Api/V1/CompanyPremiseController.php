<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCompanyPremiseRequest;
use App\Models\Company;
use App\Models\CompanyPremise;
use App\Services\Companies\CompanyPremises;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyPremiseController extends Controller
{
    public function index(Company $company, CompanyPremises $premises): JsonResponse
    {
        $rows = CompanyPremise::query()
            ->with(['district', 'contactPerson'])
            ->where('company_id', $company->id)
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $rows->map(fn (CompanyPremise $row) => $premises->present($row))->values(),
            ['districts' => $premises->districts()],
        );
    }

    public function store(SaveCompanyPremiseRequest $request, Company $company, CompanyPremises $premises): JsonResponse
    {
        $row = $premises->create($company, $request->validated(), $request->user());

        return ApiResponse::success($premises->present($row), null, 201);
    }

    public function update(SaveCompanyPremiseRequest $request, CompanyPremise $companyPremise, CompanyPremises $premises): JsonResponse
    {
        $row = $premises->update($companyPremise, $request->validated(), $request->user());

        return ApiResponse::success($premises->present($row));
    }
}
