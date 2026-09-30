<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\LicensesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Licenses\ChangeLicenseStatusRequest;
use App\Http\Requests\Licenses\StorePreviousLicenseRequest;
use App\Models\License;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Licenses\LicenseDirectory;
use App\Services\Licenses\LicenseStatusChanges;
use App\Services\Licenses\PreviousLicense;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LicenseController extends Controller
{
    public function __construct(
        private LicenseDirectory $directory,
        private LicenseStatusChanges $changes,
        private ActivityLogger $logger,
    ) {}

    public function previous(Request $request, PreviousLicense $previous): JsonResponse
    {
        return ApiResponse::success($previous->preview(
            $request->string('licensable_type')->value(),
            $request->filled('valid_to') ? $request->string('valid_to')->value() : null,
        ));
    }

    public function storePrevious(StorePreviousLicenseRequest $request, PreviousLicense $previous): JsonResponse
    {
        $license = $previous->store($request->validated(), $this->actor($request));

        return ApiResponse::success($this->directory->present($license), null, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->directory->paginate($request, $this->actor($request));

        return ApiResponse::success(
            collect($page->items())->map(fn (License $license) => $this->directory->present($license))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(License $license): JsonResponse
    {
        return ApiResponse::success($this->directory->present($license));
    }

    public function certificate(License $license, Request $request): JsonResponse
    {
        if (! is_string($license->certificate_path) || ! Storage::disk('local')->exists($license->certificate_path)) {
            abort(404, 'Certificate not found.');
        }

        $expires = now()->addMinutes(5);
        $url = Storage::disk('local')->temporaryUrl($license->certificate_path, $expires);
        $this->logger->log(
            'downloaded',
            'Opened certificate '.$license->license_no,
            'license',
            $license->id,
            $this->actor($request),
        );

        return ApiResponse::success([
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
        ]);
    }

    public function suspend(ChangeLicenseStatusRequest $request, License $license): JsonResponse
    {
        $license = $this->changes->change($license, 'suspend', $request->validated(), $this->actor($request));

        return ApiResponse::success($this->directory->present($license));
    }

    public function cancel(ChangeLicenseStatusRequest $request, License $license): JsonResponse
    {
        $license = $this->changes->change($license, 'cancel', $request->validated(), $this->actor($request));

        return ApiResponse::success($this->directory->present($license));
    }

    public function restore(ChangeLicenseStatusRequest $request, License $license): JsonResponse
    {
        $license = $this->changes->change($license, 'restore', $request->validated(), $this->actor($request));

        return ApiResponse::success($this->directory->present($license));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $actor = $this->actor($request);
        $licenses = $this->directory->query($request, $actor)->orderByDesc('issued_at')->get();
        $rows = $licenses->map(function (License $license) {
            $row = $this->directory->present($license);

            return [
                $row['license_no'],
                $row['applicant_name'],
                $row['licensable_type'],
                $row['license_kind'],
                $row['district_name'] ?? '',
                $row['valid_from'],
                $row['valid_to'],
                $row['status'],
                $row['documents_status'],
            ];
        });
        $this->logger->log('exported', 'Exported the licenses list.', 'license', 0, $actor, null, ['count' => $rows->count()]);

        return Excel::download(new LicensesExport($rows), 'licenses.xlsx');
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
