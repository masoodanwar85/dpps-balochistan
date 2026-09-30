<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Document;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\DocumentWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DocumentController extends Controller
{
    public function index(Request $request, Company $company, DocumentStore $store): JsonResponse
    {
        $category = (string) $request->query('category', '');
        $rows = Document::query()
            ->with('documentType')
            ->where('documentable_type', 'company')
            ->where('documentable_id', $company->id)
            ->whereNull('replaced_by_id')
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('documentType', fn ($type) => $type->where('category', $category));
            })
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(
            $rows->map(fn (Document $document) => $store->present($document))->values(),
            ['document_types' => $store->typesForCompany()],
        );
    }

    public function indexDealer(Request $request, Dealer $dealer, DocumentStore $store): JsonResponse
    {
        return ApiResponse::success(
            $this->currentDocuments('dealer', $dealer->id, (string) $request->query('category', ''))
                ->map(fn (Document $document) => $store->present($document))
                ->values(),
            ['document_types' => $store->typesFor('dealer')],
        );
    }

    public function storeDealer(SaveDocumentRequest $request, Dealer $dealer, DocumentStore $store): JsonResponse
    {
        try {
            $document = $store->createForDealer($dealer, $request->file('file'), $request->validated(), $request->user());
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($store->present($document), null, 201);
    }

    public function store(SaveDocumentRequest $request, Company $company, DocumentStore $store): JsonResponse
    {
        try {
            $document = $store->create($company, $request->file('file'), $request->validated(), $request->user());
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($store->present($document), null, 201);
    }

    public function update(UpdateDocumentRequest $request, Document $document, DocumentStore $store): JsonResponse
    {
        $document = $store->update($document, $request->validated(), $request->user());

        return ApiResponse::success($store->present($document));
    }

    public function replace(SaveDocumentRequest $request, Document $document, DocumentStore $store): JsonResponse
    {
        try {
            $created = $store->replace($document, $request->file('file'), $request->validated(), $request->user());
        } catch (DocumentWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($store->present($created), null, 201);
    }

    public function verify(Request $request, Document $document, DocumentStore $store): JsonResponse
    {
        $document = $store->verify($document, $request->user());

        return ApiResponse::success($store->present($document));
    }

    public function downloadUrl(Request $request, Document $document, DocumentStore $store): JsonResponse
    {
        return ApiResponse::success($store->downloadUrl($document, $request->user()));
    }

    public function history(Request $request, Document $document, DocumentStore $store): JsonResponse
    {
        $permission = $document->documentable_type === 'dealer' ? 'dealers.view' : 'companies.view';
        abort_unless($request->user()?->can($permission) ?? false, 403);

        $rows = collect($store->history($document))
            ->map(fn (Document $row) => $store->present($row))
            ->values();

        return ApiResponse::success($rows);
    }

    /**
     * @return Collection<int, Document>
     */
    private function currentDocuments(string $type, int $id, string $category)
    {
        return Document::query()
            ->with('documentType')
            ->where('documentable_type', $type)
            ->where('documentable_id', $id)
            ->whereNull('replaced_by_id')
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('documentType', fn ($typeQuery) => $typeQuery->where('category', $category));
            })
            ->orderByDesc('id')
            ->get();
    }
}
