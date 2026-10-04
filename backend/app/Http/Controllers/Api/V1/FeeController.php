<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Settings\UpdateFeesRequest;
use App\Services\Settings\FeeStructures;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeController
{
    public function __construct(private FeeStructures $fees) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.manage') ?? false, 403);

        return ApiResponse::success($this->fees->list());
    }

    public function update(UpdateFeesRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->fees->update($request->validated('fees'), $request->user()),
        );
    }
}
