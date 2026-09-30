<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreChecklistDocumentRequest;
use App\Http\Requests\Applications\UpdateChecklistItemRequest;
use App\Models\ApplicationChecklistItem;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\Applications\ApplicationChecklist;
use App\Services\Applications\ApplicationPresenter;
use App\Services\Documents\DocumentWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ApplicationChecklistController extends Controller
{
    public function __construct(
        private ApplicationChecklist $checklist,
        private ApplicationPresenter $presenter,
    ) {}

    public function update(
        UpdateChecklistItemRequest $request,
        LicenseApplication $application,
        ApplicationChecklistItem $applicationChecklistItem,
    ): JsonResponse {
        $this->checklist->update($application, $applicationChecklistItem, $request->validated(), $this->actor($request));

        return ApiResponse::success($this->presenter->detail($application->refresh()));
    }

    public function upload(
        StoreChecklistDocumentRequest $request,
        LicenseApplication $application,
        ApplicationChecklistItem $applicationChecklistItem,
    ): JsonResponse {
        $actor = $this->actor($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['Choose a file.'],
            ]);
        }

        try {
            $this->checklist->upload(
                $application,
                $applicationChecklistItem,
                $file,
                $request->validated(),
                $actor,
            );
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($this->presenter->detail($application->refresh()), status: 201);
    }

    private function actor(StoreChecklistDocumentRequest|UpdateChecklistItemRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
