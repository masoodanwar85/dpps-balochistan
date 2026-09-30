<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveDocumentTypeRequest;
use App\Models\DocumentType;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = DocumentType::query();
        ListQuery::search($request, $query, ['name']);
        ListQuery::active($request, $query);

        if ($request->filled('filter.category')) {
            $query->where('category', $request->string('filter.category')->value());
        }

        if ($request->filled('filter.applies_to')) {
            $query->where('applies_to', $request->string('filter.applies_to')->value());
        }

        $page = ListQuery::paginate($request, $query, ['name'], 'name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (DocumentType $documentType) => $this->payload($documentType))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(DocumentType $documentType): JsonResponse
    {
        return ApiResponse::success($this->payload($documentType));
    }

    public function store(SaveDocumentTypeRequest $request): JsonResponse
    {
        $documentType = $this->catalog->create(
            new DocumentType,
            $request->validated(),
            $request->user(),
            'document_type',
            'Created document type.',
        );

        return ApiResponse::success($this->payload($documentType), status: 201);
    }

    public function update(SaveDocumentTypeRequest $request, DocumentType $documentType): JsonResponse
    {
        $documentType = $this->catalog->update(
            $documentType,
            $request->validated(),
            $request->user(),
            'document_type',
            'Updated document type.',
        );

        return ApiResponse::success($this->payload($documentType));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DocumentType $documentType): array
    {
        return [
            'id' => $documentType->id,
            'name' => $documentType->name,
            'category' => $documentType->category,
            'applies_to' => $documentType->applies_to,
            'has_expiry' => $documentType->has_expiry,
            'is_active' => $documentType->is_active,
        ];
    }
}
