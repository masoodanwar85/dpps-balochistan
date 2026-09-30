<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicVerificationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $license = License::query()->where('verification_token', $token)->first();

        if (! $license instanceof License) {
            return ApiResponse::error(['resource' => ['Certificate not found.']], 404);
        }

        $application = $license->application_id
            ? LicenseApplication::withTrashed()->find($license->application_id)
            : null;
        $name = $application instanceof LicenseApplication
            ? $application->applicantName()
            : ($license->licensable_type === 'company'
                ? Company::withTrashed()->find($license->licensable_id)?->name
                : Dealer::withTrashed()->find($license->licensable_id)?->shop_name);

        return ApiResponse::success([
            'name' => $name ?: 'License holder',
            'type' => $license->licensable_type === 'dealer' ? 'Pesticide dealer' : 'Pesticide company',
            'license_no' => $license->license_no,
            'valid_from' => $license->valid_from?->toDateString(),
            'valid_to' => $license->valid_to?->toDateString(),
            'status' => $license->status,
            'district_name' => $license->licensable_type === 'dealer'
                ? Dealer::withTrashed()->find($license->licensable_id)?->district?->name
                : null,
            'banner' => $license->status === 'active' ? null : 'This certificate is no longer valid.',
        ]);
    }
}
