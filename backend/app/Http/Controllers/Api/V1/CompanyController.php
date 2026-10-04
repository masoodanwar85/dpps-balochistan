<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\CompaniesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\DeleteCompanyRequest;
use App\Http\Requests\Companies\SaveCompanyRequest;
use App\Models\Company;
use App\Models\License;
use App\Models\Province;
use App\Services\ActivityLogger;
use App\Services\Companies\CompanyDirectory;
use App\Services\Companies\CompanyName;
use App\Services\Companies\CompanyWarningException;
use App\Services\Companies\CompanyWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyController extends Controller
{
    public function index(Request $request, CompanyDirectory $directory): JsonResponse
    {
        if (! $request->query->has('per_page')) {
            $request->query->set('per_page', 25);
        }

        $page = ListQuery::paginate(
            $request,
            $directory->query($request),
            ['company_code', 'name', 'status', 'city'],
            'name',
        );

        return ApiResponse::success(
            $page->getCollection()->map(fn (Company $company) => $this->payload($company))->values(),
            [
                ...ListQuery::meta($page),
                'provinces' => $this->provinces(),
            ],
        );
    }

    public function show(Company $company): JsonResponse
    {
        $company->load('province');

        return ApiResponse::success($this->payload($company), [
            'provinces' => $this->provinces(),
        ]);
    }

    public function checkDuplicate(Request $request, CompanyName $names): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $ntn = trim((string) $request->input('ntn', ''));
        $ignoreId = $request->filled('ignore_id') ? $request->integer('ignore_id') : null;

        if ($request->user()?->user_type === 'company') {
            $ignoreId = $request->user()->company_id;
        }

        $ntnTaken = $ntn !== '' && Company::query()
            ->visibleTo($request->user())
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('ntn', $ntn)
            ->exists();

        $matches = $name === '' ? [] : $names->similar($name, $ignoreId);

        return ApiResponse::success([
            'ntn_taken' => $ntnTaken,
            'matches' => $matches,
            'warnings' => array_column($matches, 'message'),
        ]);
    }

    public function store(SaveCompanyRequest $request, CompanyWriter $writer): JsonResponse
    {
        try {
            $company = $writer->create($request->validated(), $request->user());
        } catch (CompanyWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $company->load('province');

        return ApiResponse::success($this->payload($company), status: 201);
    }

    public function update(SaveCompanyRequest $request, Company $company, CompanyWriter $writer): JsonResponse
    {
        try {
            $company = $writer->update($company, $request->validated(), $request->user());
        } catch (CompanyWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $company->load('province');

        return ApiResponse::success($this->payload($company));
    }

    public function destroy(DeleteCompanyRequest $request, Company $company, CompanyWriter $writer): JsonResponse
    {
        $writer->delete($company, $request->string('reason')->value(), $request->user());

        return ApiResponse::success(['deleted' => true]);
    }

    public function export(Request $request, CompanyDirectory $directory, ActivityLogger $logger): BinaryFileResponse
    {
        abort_unless($request->user()?->can('companies.view') ?? false, 403);

        $companies = $directory->query($request)->orderBy('name')->get();
        $logger->log(
            'exported',
            'Exported the companies list.',
            'company',
            0,
            $request->user(),
            null,
            [
                'count' => $companies->count(),
                'search' => $request->input('search'),
                'filter' => $request->input('filter'),
            ],
        );

        $rows = $companies->map(fn (Company $company) => [
            $company->company_code,
            $company->name,
            (string) ($company->ntn ?? ''),
            $this->displayDate($company->getAttribute('expiry_date')),
            $company->status,
        ]);

        return Excel::download(new CompaniesExport($rows), 'companies.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Company $company): array
    {
        $expiry = $company->getAttribute('expiry_date');

        if ($expiry === null && ! array_key_exists('expiry_date', $company->getAttributes())) {
            $expiry = License::query()
                ->where('licensable_type', 'company')
                ->where('licensable_id', $company->id)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('valid_to')
                ->value('valid_to');
        }

        return [
            'id' => $company->id,
            'company_code' => $company->company_code,
            'name' => $company->name,
            'legal_type' => $company->legal_type,
            'ntn' => $company->ntn,
            'incorporation_no' => $company->incorporation_no,
            'incorporation_date' => $company->incorporation_date?->toDateString(),
            'head_office_address' => $company->head_office_address,
            'city' => $company->city,
            'province_id' => $company->province_id,
            'province_name' => $company->province?->name,
            'landline' => $company->landline,
            'mobile' => $company->mobile,
            'email' => $company->email,
            'website' => $company->website,
            'pcpa_member' => $company->pcpa_member,
            'croplife_member' => $company->croplife_member,
            'membership_no' => $company->membership_no,
            'csr' => $company->csr,
            'rnd' => $company->rnd,
            'status' => $company->status,
            'expiry_date' => $expiry ? Carbon::parse($expiry)->toDateString() : null,
        ];
    }

    private function displayDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d-m-Y');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function provinces(): array
    {
        return Province::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'id' => $province->id,
                'name' => $province->name,
            ])
            ->all();
    }
}
