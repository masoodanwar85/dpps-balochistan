<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\DeleteCompanyProductRequest;
use App\Http\Requests\Companies\SaveCompanyProductRequest;
use App\Models\Company;
use App\Models\CompanyProduct;
use App\Services\Companies\CompanyProducts;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyProductController extends Controller
{
    public function index(Company $company, CompanyProducts $products): JsonResponse
    {
        $rows = CompanyProduct::query()
            ->with('product')
            ->where('company_id', $company->id)
            ->orderBy('brand_name')
            ->get();

        return ApiResponse::success(
            $rows->map(fn (CompanyProduct $row) => $products->present($row))->values(),
            ['products' => $products->choices()],
        );
    }

    public function store(SaveCompanyProductRequest $request, Company $company, CompanyProducts $products): JsonResponse
    {
        $row = $products->create($company, $request->validated(), $request->user());

        return ApiResponse::success($products->present($row), null, 201);
    }

    public function update(SaveCompanyProductRequest $request, CompanyProduct $companyProduct, CompanyProducts $products): JsonResponse
    {
        $row = $products->update($companyProduct, $request->validated(), $request->user());

        return ApiResponse::success($products->present($row));
    }

    public function destroy(DeleteCompanyProductRequest $request, CompanyProduct $companyProduct, CompanyProducts $products): JsonResponse
    {
        $products->delete($companyProduct, $request->validated('reason'), $request->user());

        return ApiResponse::success(['deleted' => true]);
    }
}
