<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Checklists\CopyChecklistItemsRequest;
use App\Http\Requests\Checklists\ReorderChecklistItemsRequest;
use App\Http\Requests\Checklists\SaveChecklistItemRequest;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Services\Checklists\ChecklistEditor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ChecklistTemplateController
{
    public function __construct(private ChecklistEditor $editor) {}

    public function index(): JsonResponse
    {
        $templates = ChecklistTemplate::query()->withCount('items')->orderBy('version_no')->get();
        $families = [
            ['company', 'new', 'Company – New'],
            ['company', 'renewal', 'Company – Renewal'],
            ['dealer', 'new', 'Dealer – New'],
            ['dealer', 'renewal', 'Dealer – Renewal'],
        ];

        $rows = [];

        foreach ($families as [$entityType, $applicationType, $label]) {
            $group = $templates->where('entity_type', $entityType)->where('application_type', $applicationType);
            $published = $group->firstWhere('status', 'published');
            $draft = $group->firstWhere('status', 'draft');
            $named = $published ?? $draft;

            $rows[] = [
                'entity_type' => $entityType,
                'application_type' => $applicationType,
                'name' => $named->name ?? $label,
                'published' => $published ? $this->summary($published) : null,
                'draft' => $draft ? $this->summary($draft) : null,
            ];
        }

        return ApiResponse::success($rows);
    }

    public function show(ChecklistTemplate $checklistTemplate): JsonResponse
    {
        return ApiResponse::success($this->payload($checklistTemplate));
    }

    public function newVersion(ChecklistTemplate $checklistTemplate): JsonResponse
    {
        $draft = $this->editor->startVersion($checklistTemplate, request()->user());

        return ApiResponse::success($this->payload($draft), status: 201);
    }

    public function publish(ChecklistTemplate $checklistTemplate): JsonResponse
    {
        $published = $this->editor->publish($checklistTemplate, request()->user());

        return ApiResponse::success($this->payload($published));
    }

    public function copyFrom(CopyChecklistItemsRequest $request, ChecklistTemplate $checklistTemplate): JsonResponse
    {
        $source = ChecklistTemplate::query()->findOrFail($request->integer('source_template_id'));
        $this->editor->copyFrom($checklistTemplate, $source, $request->user());

        return ApiResponse::success($this->payload($checklistTemplate));
    }

    public function storeItem(SaveChecklistItemRequest $request, ChecklistTemplate $checklistTemplate): JsonResponse
    {
        $item = $this->editor->createItem($checklistTemplate, $request->validated(), $request->user());

        return ApiResponse::success($this->itemPayload($item), status: 201);
    }

    public function updateItem(SaveChecklistItemRequest $request, ChecklistTemplate $checklistTemplate, ChecklistItem $checklistItem): JsonResponse
    {
        $this->ownedItem($checklistTemplate, $checklistItem);
        $item = $this->editor->updateItem($checklistTemplate, $checklistItem, $request->validated(), $request->user());

        return ApiResponse::success($this->itemPayload($item));
    }

    public function destroyItem(ChecklistTemplate $checklistTemplate, ChecklistItem $checklistItem): JsonResponse
    {
        $this->ownedItem($checklistTemplate, $checklistItem);
        $this->editor->deleteItem($checklistTemplate, $checklistItem, request()->user());

        return ApiResponse::success(['deleted' => true]);
    }

    public function reorder(ReorderChecklistItemsRequest $request, ChecklistTemplate $checklistTemplate): JsonResponse
    {
        $this->editor->reorder($checklistTemplate, $request->validated('item_ids'), $request->user());

        return ApiResponse::success($this->payload($checklistTemplate));
    }

    private function ownedItem(ChecklistTemplate $template, ChecklistItem $item): void
    {
        if ($item->template_id !== $template->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ChecklistTemplate $template): array
    {
        return [
            'id' => $template->id,
            'version_no' => $template->version_no,
            'item_count' => $template->items_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ChecklistTemplate $template): array
    {
        $template->load(['items' => fn ($query) => $query->orderBy('sort_order')]);
        $published = ChecklistTemplate::query()
            ->where('entity_type', $template->entity_type)
            ->where('application_type', $template->application_type)
            ->where('status', 'published')
            ->first();

        return [
            'id' => $template->id,
            'entity_type' => $template->entity_type,
            'application_type' => $template->application_type,
            'version_no' => $template->version_no,
            'name' => $template->name,
            'status' => $template->status,
            'published_at' => $template->published_at?->toIso8601String(),
            'editable' => $template->status === 'draft',
            'published_version_no' => $published?->version_no,
            'items' => $template->items->map(fn (ChecklistItem $item) => $this->itemPayload($item))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'sort_order' => $item->sort_order,
            'annex_code' => $item->annex_code,
            'title' => $item->title,
            'description' => $item->description,
            'form_reference' => $item->form_reference,
            'is_required' => $item->is_required,
            'requires_upload' => $item->requires_upload,
            'allowed_file_types' => $item->allowed_file_types,
            'max_files' => $item->max_files,
            'attestation_required' => $item->attestation_required,
            'requires_validity_dates' => $item->requires_validity_dates,
            'must_cover_license_period' => $item->must_cover_license_period,
            'portal_uploadable' => $item->portal_uploadable,
        ];
    }
}
