<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ActivityLogsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Services\Activity\ActivityLogDirectory;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityLogController extends Controller
{
    public function index(ActivityLogFilterRequest $request, ActivityLogDirectory $directory): JsonResponse
    {
        if (! $request->query->has('per_page')) {
            $request->query->set('per_page', 25);
        }

        if (! $request->query->has('sort')) {
            $request->query->set('sort', '-created_at');
        }

        $page = ListQuery::paginate(
            $request,
            $directory->query($request),
            ['created_at'],
            'created_at',
        );

        return ApiResponse::success(
            $page->getCollection()->map(fn (ActivityLog $log) => $directory->present($log))->values(),
            [
                ...ListQuery::meta($page),
                ...$directory->options(),
            ],
        );
    }

    public function export(
        ActivityLogFilterRequest $request,
        ActivityLogDirectory $directory,
        ActivityLogger $logger,
    ): BinaryFileResponse {
        abort_unless($request->user()?->can('exports.run') ?? false, 403);

        $logs = $directory->query($request)->orderByDesc('created_at')->orderByDesc('id')->get();
        $logger->log(
            'exported',
            'Exported the activity log.',
            'activity_log',
            0,
            $request->user(),
            null,
            [
                'count' => $logs->count(),
                'user_id' => $request->input('user_id'),
                'action' => $request->input('action'),
                'module' => $request->input('module'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        );

        return Excel::download(new ActivityLogsExport($directory->exportRows($logs)), 'activity-logs.xlsx');
    }
}
