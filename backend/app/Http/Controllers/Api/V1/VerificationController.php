<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Verifications\RejectVerificationRequest;
use App\Models\User;
use App\Services\Verifications\VerificationQueue;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private VerificationQueue $queue) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->value() ?: 'staff';
        $result = $this->queue->list($this->actor($request), $type);

        return ApiResponse::success($result['rows'], ['counts' => $result['counts']]);
    }

    public function show(Request $request, string $type, int $id): JsonResponse
    {
        return ApiResponse::success($this->queue->show($this->actor($request), $type, $id));
    }

    public function approve(Request $request, string $type, int $id): JsonResponse
    {
        $this->queue->approve($this->actor($request), $type, $id);

        return ApiResponse::success(['status' => 'approved']);
    }

    public function reject(RejectVerificationRequest $request, string $type, int $id): JsonResponse
    {
        $this->queue->reject($this->actor($request), $type, $id, $request->string('reason')->value());

        return ApiResponse::success(['status' => 'rejected']);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
