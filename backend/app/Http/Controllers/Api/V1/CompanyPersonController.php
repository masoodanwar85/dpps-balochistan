<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CheckCompanyPersonRequest;
use App\Http\Requests\Companies\EndCompanyPersonRequest;
use App\Http\Requests\Companies\StoreCompanyPersonRequest;
use App\Http\Requests\Companies\UpdateCompanyPersonRequest;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Services\Companies\CompanyPeople;
use App\Services\Companies\CompanyProfile;
use App\Services\Persons\PersonWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyPersonController extends Controller
{
    public function index(Company $company, CompanyPeople $people, CompanyProfile $profile): JsonResponse
    {
        $rows = CompanyPerson::query()
            ->with(['person.qualifications.qualification'])
            ->where('company_id', $company->id)
            ->orderBy('role')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success(
            $rows->map(fn (CompanyPerson $row) => $people->present($row))->values(),
            [
                'qualifications' => $people->qualifications(),
                'verified_technical_staff' => $profile->verifiedTechnicalStaff($company),
                'minimum_technical_staff' => $profile->minimumTechnicalStaff(),
            ],
        );
    }

    public function check(CheckCompanyPersonRequest $request, Company $company, CompanyPeople $people): JsonResponse
    {
        return ApiResponse::success(
            $people->preview($request->validated('cnic'), $request->validated('role'), $request->user())
        );
    }

    public function store(StoreCompanyPersonRequest $request, Company $company, CompanyPeople $people): JsonResponse
    {
        try {
            $assignment = $people->create($company, $request->validated(), $request->user());
        } catch (PersonWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($people->present($assignment), null, 201);
    }

    public function update(UpdateCompanyPersonRequest $request, CompanyPerson $companyPerson, CompanyPeople $people): JsonResponse
    {
        $assignment = $people->update($companyPerson, $request->validated(), $request->user());

        return ApiResponse::success($people->present($assignment));
    }

    public function end(EndCompanyPersonRequest $request, CompanyPerson $companyPerson, CompanyPeople $people): JsonResponse
    {
        $result = $people->end($companyPerson, $request->validated(), $request->user());

        return ApiResponse::success([
            ...$people->present($result['assignment']),
            'staff_note' => $result['staff_note'],
        ]);
    }

    public function verify(Request $request, CompanyPerson $companyPerson, CompanyPeople $people): JsonResponse
    {
        $assignment = $people->verify($companyPerson, $request->user());

        return ApiResponse::success($people->present($assignment));
    }
}
