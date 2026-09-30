<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\CloseApplicationRequest;
use App\Http\Requests\Applications\StageActionRequest;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Http\Requests\Applications\UpdateApplicationRequest;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\Applications\ApplicationActivity;
use App\Services\Applications\ApplicationDirectory;
use App\Services\Applications\ApplicationPresenter;
use App\Services\Applications\ApplicationStages;
use App\Services\Applications\ApplicationWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationDirectory $directory,
        private ApplicationPresenter $presenter,
        private ApplicationWriter $writer,
        private ApplicationStages $stages,
        private ApplicationActivity $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $page = $this->directory->paginate($request, $actor);

        return ApiResponse::success(
            collect($page->items())->map(fn (LicenseApplication $application) => $this->presenter->summary($application))->values(),
            array_merge(ListQuery::meta($page), $this->directory->meta($actor)),
        );
    }

    public function show(LicenseApplication $application): JsonResponse
    {
        return ApiResponse::success($this->presenter->detail($application));
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $application = $this->writer->create($request->validated(), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application), status: 201);
    }

    public function update(UpdateApplicationRequest $request, LicenseApplication $application): JsonResponse
    {
        $application = $this->writer->update($application, $request->validated(), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application));
    }

    public function complete(StageActionRequest $request, LicenseApplication $application, WorkflowStage $workflowStage): JsonResponse
    {
        $application = $this->stages->complete(
            $application,
            $workflowStage,
            $this->actor($request),
            $request->validated('remarks'),
        );

        return ApiResponse::success($this->presenter->detail($application));
    }

    public function skip(StageActionRequest $request, LicenseApplication $application, WorkflowStage $workflowStage): JsonResponse
    {
        $application = $this->stages->skip(
            $application,
            $workflowStage,
            $this->actor($request),
            (string) $request->validated('remarks'),
        );

        return ApiResponse::success($this->presenter->detail($application));
    }

    public function reject(CloseApplicationRequest $request, LicenseApplication $application): JsonResponse
    {
        $application = $this->writer->reject($application, (string) $request->validated('reason'), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application));
    }

    public function withdraw(CloseApplicationRequest $request, LicenseApplication $application): JsonResponse
    {
        $application = $this->writer->withdraw($application, (string) $request->validated('reason'), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application));
    }

    public function activity(LicenseApplication $application): JsonResponse
    {
        $rows = $this->activity->forApplication($application)->map(fn ($log) => $this->activity->present($log))->values();

        return ApiResponse::success($rows);
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
