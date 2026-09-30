<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\ResolveImportExceptionRequest;
use App\Http\Requests\Imports\StoreImportRequest;
use App\Models\ImportBatch;
use App\Models\ImportException;
use App\Services\Imports\ImportBatchService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->query->has('per_page')) {
            $request->query->set('per_page', 25);
        }

        if (! $request->query->has('sort')) {
            $request->query->set('sort', '-started_at');
        }

        $page = ListQuery::paginate(
            $request,
            ImportBatch::query()->with('importer:id,name'),
            ['started_at', 'file_name', 'status'],
            'started_at',
        );

        return ApiResponse::success(
            $page->getCollection()->map(fn (ImportBatch $batch) => $this->batchPayload($batch))->values(),
            ListQuery::meta($page),
        );
    }

    public function store(StoreImportRequest $request, ImportBatchService $imports): JsonResponse
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return ApiResponse::error(['file' => ['Choose an Excel file.']], 422);
        }

        $batch = $imports->store(
            $file,
            $request->string('type')->value(),
            $request->string('mode')->value(),
            $request->user(),
        );

        return ApiResponse::success($this->detail($batch), null, 201);
    }

    public function show(Request $request, ImportBatch $importBatch): JsonResponse
    {
        abort_unless($request->user()?->can('imports.run') || $request->user()?->can('imports.resolve'), 403);

        return ApiResponse::success($this->detail($importBatch, $request));
    }

    public function run(Request $request, ImportBatch $importBatch, ImportBatchService $imports): JsonResponse
    {
        abort_unless($request->user()?->can('imports.run') ?? false, 403);

        return ApiResponse::success($this->detail($imports->commit($importBatch, $request->user())));
    }

    public function showException(Request $request, ImportException $importException): JsonResponse
    {
        abort_unless($request->user()?->can('imports.run') || $request->user()?->can('imports.resolve'), 403);

        return ApiResponse::success($this->exceptionPayload($importException));
    }

    public function updateException(
        ResolveImportExceptionRequest $request,
        ImportException $importException,
        ImportBatchService $imports,
    ): JsonResponse {
        $exception = $imports->resolve($importException, [
            'resolution' => $request->string('resolution')->value(),
            'target_id' => $request->filled('target_id') ? $request->integer('target_id') : null,
            'fields' => $request->input('fields', []),
            'comment' => $request->input('comment'),
        ], $request->user());

        return ApiResponse::success($this->exceptionPayload($exception));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ImportBatch $batch, ?Request $request = null): array
    {
        $batch->load('importer:id,name');
        $query = $batch->exceptions()->orderBy('row_no')->orderBy('id');

        if ($request?->filled('issue')) {
            $query->where('issue_type', $request->string('issue')->value());
        }

        if ($request?->filled('status')) {
            $query->where('resolution_status', $request->string('status')->value());
        }

        return [
            ...$this->batchPayload($batch),
            'exceptions' => $query->get()->map(fn (ImportException $exception) => $this->exceptionPayload($exception))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'file_name' => $batch->file_name,
            'sheet_name' => $batch->sheet_name,
            'type' => $batch->type(),
            'total_rows' => $batch->total_rows,
            'success_rows' => $batch->success_rows,
            'exception_rows' => $batch->exception_rows,
            'open_exceptions' => $batch->exceptions()->where('resolution_status', 'open')->count(),
            'status' => $batch->status,
            'committed' => $batch->committed(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'finished_at' => $batch->finished_at?->toIso8601String(),
            'importer' => $batch->importer?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionPayload(ImportException $exception): array
    {
        $notes = json_decode((string) $exception->notes, true);

        return [
            'id' => $exception->id,
            'batch_id' => $exception->batch_id,
            'row_no' => $exception->row_no,
            'issue_type' => $exception->issue_type,
            'issue_details' => $exception->issue_details,
            'resolution_status' => $exception->resolution_status,
            'fields_needed' => $exception->raw_data['fields_needed'] ?? [],
            'match_id' => $exception->raw_data['match_id'] ?? null,
            'decision' => is_array($notes) ? ($notes['decision'] ?? null) : null,
            'notes' => is_array($notes) ? ($notes['comment'] ?? null) : $exception->notes,
        ];
    }
}
