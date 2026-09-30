<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveQualificationRequest;
use App\Models\Qualification;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualificationController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = Qualification::query();
        ListQuery::search($request, $query, ['name']);
        ListQuery::active($request, $query);
        $page = ListQuery::paginate($request, $query, ['name'], 'name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (Qualification $qualification) => $this->payload($qualification))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(Qualification $qualification): JsonResponse
    {
        return ApiResponse::success($this->payload($qualification));
    }

    public function store(SaveQualificationRequest $request): JsonResponse
    {
        $qualification = $this->catalog->create(
            new Qualification,
            $request->validated(),
            $request->user(),
            'qualification',
            'Created qualification.',
        );

        return ApiResponse::success($this->payload($qualification), status: 201);
    }

    public function update(SaveQualificationRequest $request, Qualification $qualification): JsonResponse
    {
        $qualification = $this->catalog->update(
            $qualification,
            $request->validated(),
            $request->user(),
            'qualification',
            'Updated qualification.',
        );

        return ApiResponse::success($this->payload($qualification));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Qualification $qualification): array
    {
        return [
            'id' => $qualification->id,
            'name' => $qualification->name,
            'is_agriculture_degree' => $qualification->is_agriculture_degree,
            'is_active' => $qualification->is_active,
        ];
    }
}
