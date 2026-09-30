<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveProvinceRequest;
use App\Models\Province;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = Province::query();
        ListQuery::search($request, $query, ['name']);
        ListQuery::active($request, $query);
        $page = ListQuery::paginate($request, $query, ['name'], 'name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (Province $province) => $this->payload($province))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(Province $province): JsonResponse
    {
        return ApiResponse::success($this->payload($province));
    }

    public function store(SaveProvinceRequest $request): JsonResponse
    {
        $province = $this->catalog->create(
            new Province,
            $request->validated(),
            $request->user(),
            'province',
            'Created province.',
        );

        return ApiResponse::success($this->payload($province), status: 201);
    }

    public function update(SaveProvinceRequest $request, Province $province): JsonResponse
    {
        $province = $this->catalog->update(
            $province,
            $request->validated(),
            $request->user(),
            'province',
            'Updated province.',
        );

        return ApiResponse::success($this->payload($province));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Province $province): array
    {
        return [
            'id' => $province->id,
            'name' => $province->name,
            'is_active' => $province->is_active,
        ];
    }
}
