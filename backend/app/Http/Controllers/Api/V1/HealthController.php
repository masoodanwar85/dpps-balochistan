<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1 as ok');
        } catch (Throwable) {
            return ApiResponse::error([
                'database' => ['Database connection failed.'],
            ], 503);
        }

        return ApiResponse::success([
            'status' => 'ok',
            'database' => 'ok',
        ]);
    }
}
