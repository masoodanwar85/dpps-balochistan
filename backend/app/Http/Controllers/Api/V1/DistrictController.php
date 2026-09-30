<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveDistrictRequest;
use App\Models\District;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = District::query();
        ListQuery::search($request, $query, ['name', 'code']);
        ListQuery::active($request, $query);
        $page = ListQuery::paginate($request, $query, ['name', 'code'], 'name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (District $district) => $this->payload($district))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(District $district): JsonResponse
    {
        return ApiResponse::success($this->payload($district));
    }

    public function store(SaveDistrictRequest $request): JsonResponse
    {
        $district = $this->catalog->create(
            new District,
            $request->validated(),
            $request->user(),
            'district',
            'Created district.',
        );

        return ApiResponse::success($this->payload($district), status: 201);
    }

    public function update(SaveDistrictRequest $request, District $district): JsonResponse
    {
        $district = $this->catalog->update(
            $district,
            $request->validated(),
            $request->user(),
            'district',
            'Updated district.',
        );

        return ApiResponse::success($this->payload($district));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(District $district): array
    {
        return [
            'id' => $district->id,
            'name' => $district->name,
            'code' => $district->code,
            'is_active' => $district->is_active,
        ];
    }
}
