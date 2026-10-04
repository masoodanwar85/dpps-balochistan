<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CheckCompanyPersonRequest;
use App\Http\Requests\Companies\EndCompanyPersonRequest;
use App\Http\Requests\Companies\SaveCompanyProductRequest;
use App\Http\Requests\Companies\SaveCsrRndFileRequest;
use App\Http\Requests\Companies\StoreCompanyPersonRequest;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Http\Requests\Portal\ResetPortalPasswordRequest;
use App\Http\Requests\Portal\StorePortalUserRequest;
use App\Http\Requests\Portal\SubmitPortalRenewalRequest;
use App\Http\Requests\Portal\UploadPortalChecklistRequest;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\Document;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Companies\CompanyPeople;
use App\Services\Companies\CompanyProducts;
use App\Services\Companies\CsrRndMediaStore;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\DocumentWarningException;
use App\Services\Notifications\InAppNotifications;
use App\Services\Persons\PersonWarningException;
use App\Services\Portal\PortalAccess;
use App\Services\Portal\PortalHome;
use App\Services\Portal\PortalRenewal;
use App\Services\Portal\PortalUsers;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function __construct(
        private PortalAccess $access,
        private PortalHome $home,
        private PortalRenewal $renewal,
        private PortalUsers $portalUsers,
        private CompanyPeople $people,
        private CompanyProducts $products,
        private DocumentStore $documents,
        private CsrRndMediaStore $csrRnd,
        private InAppNotifications $notifications,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->home->dashboard($this->access->company($this->actor($request))));
    }

    public function company(Request $request): JsonResponse
    {
        return ApiResponse::success($this->home->company($this->access->company($this->actor($request))));
    }

    public function staff(Request $request): JsonResponse
    {
        $company = $this->access->company($this->actor($request));

        return ApiResponse::success($this->home->staff($company), [
            'qualifications' => $this->people->qualifications(),
        ]);
    }

    public function checkStaff(CheckCompanyPersonRequest $request): JsonResponse
    {
        $this->access->company($this->actor($request));

        return ApiResponse::success(
            $this->people->preview($request->validated('cnic'), 'technical_staff', $this->actor($request)),
        );
    }

    public function storeStaff(StoreCompanyPersonRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);
        $data = $request->validated();
        $data['role'] = 'technical_staff';

        try {
            $assignment = $this->people->create($company, $data, $actor);
        } catch (PersonWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $this->notifications->portalSubmitted(
            'Portal staff submitted',
            $company->name.' submitted technical staff '.$assignment->person?->full_name.'.',
            ['company_id' => $company->id, 'company_person_id' => $assignment->id],
        );

        return ApiResponse::success($this->people->present($assignment), null, 201);
    }

    public function endStaff(EndCompanyPersonRequest $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);
        $assignment = CompanyPerson::query()
            ->where('company_id', $company->id)
            ->where('role', 'technical_staff')
            ->whereKey($id)
            ->first();

        if (! $assignment instanceof CompanyPerson) {
            abort(404);
        }

        $result = $this->people->end($assignment, $request->validated(), $actor);

        return ApiResponse::success([
            ...$this->people->present($result['assignment']),
            'staff_note' => $result['staff_note'],
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $company = $this->access->company($this->actor($request));

        return ApiResponse::success($this->home->documents($company), [
            'document_types' => $this->documents->typesForCompany(),
        ]);
    }

    public function storeDocument(SaveDocumentRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);

        try {
            $document = $this->documents->create($company, $request->file('file'), $request->validated(), $actor);
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $this->notifications->portalSubmitted(
            'Portal document submitted',
            $company->name.' submitted document '.$document->title.'.',
            ['company_id' => $company->id, 'document_id' => $document->id],
        );

        return ApiResponse::success($this->documents->present($document), null, 201);
    }

    public function replaceDocument(SaveDocumentRequest $request, Document $document): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);

        if ($document->documentable_type !== 'company' || (int) $document->documentable_id !== $company->id) {
            abort(404);
        }

        try {
            $created = $this->documents->replace($document, $request->file('file'), $request->validated(), $actor);
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $this->notifications->portalSubmitted(
            'Portal document submitted',
            $company->name.' replaced document '.$created->title.'.',
            ['company_id' => $company->id, 'document_id' => $created->id],
        );

        return ApiResponse::success($this->documents->present($created), null, 201);
    }

    public function csrRndFiles(Request $request): JsonResponse
    {
        $company = $this->access->company($this->actor($request));

        if (! $company->csr && ! $company->rnd) {
            abort(404);
        }

        return ApiResponse::success(
            $this->csrRnd->list($company, $request->query('kind')),
            [
                'kinds' => $this->csrRnd->kindsFor($company),
                'csr' => (bool) $company->csr,
                'rnd' => (bool) $company->rnd,
            ],
        );
    }

    public function storeCsrRndFile(SaveCsrRndFileRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);

        if (! $company->csr && ! $company->rnd) {
            abort(404);
        }

        $file = $this->csrRnd->create($company, $request->file('file'), $request->validated(), $actor);

        return ApiResponse::success($this->csrRnd->present($file), status: 201);
    }

    public function products(Request $request): JsonResponse
    {
        $company = $this->access->company($this->actor($request));
        $rows = CompanyProduct::query()
            ->with('product')
            ->where('company_id', $company->id)
            ->orderBy('brand_name')
            ->get()
            ->map(fn (CompanyProduct $row) => $this->products->present($row))
            ->values();

        return ApiResponse::success($rows, ['products' => $this->products->choices()]);
    }

    public function storeProduct(SaveCompanyProductRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);
        $row = $this->products->create($company, $request->validated(), $actor);
        $this->notifications->portalSubmitted(
            'Portal product submitted',
            $company->name.' submitted product '.$row->brand_name.'.',
            ['company_id' => $company->id, 'company_product_id' => $row->id],
        );

        return ApiResponse::success($this->products->present($row), null, 201);
    }

    public function applications(Request $request): JsonResponse
    {
        return ApiResponse::success($this->home->applications($this->access->company($this->actor($request))));
    }

    public function showApplication(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success($this->home->application($this->ownApplication($request, $id)));
    }

    public function renewal(Request $request): JsonResponse
    {
        return ApiResponse::success($this->renewal->show($this->access->company($this->actor($request))));
    }

    public function startRenewal(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return ApiResponse::success($this->renewal->start($this->access->company($actor), $actor), null, 201);
    }

    public function uploadRenewalItem(UploadPortalChecklistRequest $request, int $item): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->access->company($actor);
        $file = $request->file('file');

        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => ['Choose a file.'],
            ]);
        }

        try {
            $this->renewal->upload($company, $item, $file, $request->validated(), $actor);
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($this->renewal->show($company));
    }

    public function submitRenewal(SubmitPortalRenewalRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $application = $this->renewal->submit($this->access->company($actor), $request->validated(), $actor);

        return ApiResponse::success($this->home->application($application));
    }

    public function users(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portalUsers->list($this->access->company($this->actor($request))));
    }

    public function storeUser(StorePortalUserRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $user = $this->portalUsers->create($this->access->company($actor), $request->validated(), $actor);

        return ApiResponse::success($this->portalUsers->present($user), null, 201);
    }

    public function deactivateUser(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $user = $this->portalUsers->deactivate($this->access->company($actor), $id, $actor);

        return ApiResponse::success($this->portalUsers->present($user));
    }

    public function resetUser(ResetPortalPasswordRequest $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $user = $this->portalUsers->resetPassword(
            $this->access->company($actor),
            $id,
            $request->string('password')->value(),
            $actor,
        );

        return ApiResponse::success($this->portalUsers->present($user));
    }

    private function ownApplication(Request $request, int $id): LicenseApplication
    {
        $company = $this->access->company($this->actor($request));
        $application = LicenseApplication::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->whereKey($id)
            ->first();

        if (! $application instanceof LicenseApplication) {
            abort(404);
        }

        return $application;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
