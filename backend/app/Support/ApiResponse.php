<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    public static function error(array $errors, int $status, mixed $data = null): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => null,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param  list<string>  $warnings
     */
    public static function warnings(array $warnings): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => null,
            'warnings' => array_values($warnings),
        ], 409);
    }
}
