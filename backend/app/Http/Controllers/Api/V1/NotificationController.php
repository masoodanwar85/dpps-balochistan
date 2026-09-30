<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $rows = $user->notifications()->latest()->limit(20)->get();

        return ApiResponse::success(
            $rows->map(fn (DatabaseNotification $row) => [
                'id' => $row->id,
                'title' => $row->data['title'] ?? 'Notice',
                'body' => $row->data['body'] ?? '',
                'kind' => $row->data['kind'] ?? '',
                'read_at' => $row->read_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values(),
            ['unread' => $user->unreadNotifications()->count()],
        );
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $row = $this->actor($request)->notifications()->whereKey($id)->first();

        if (! $row instanceof DatabaseNotification) {
            abort(404);
        }

        $row->markAsRead();

        return ApiResponse::success(['id' => $row->id]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->actor($request)->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['read' => true]);
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
