<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use App\Support\PermissionMatrix;
use Illuminate\Http\JsonResponse;

class PermissionController
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'modules' => PermissionMatrix::modules(),
        ]);
    }
}
