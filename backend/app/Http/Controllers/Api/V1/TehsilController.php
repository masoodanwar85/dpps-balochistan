<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveTehsilRequest;
use App\Models\Tehsil;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TehsilController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tehsil::query()->with('district');
        ListQuery::search($request, $query, ['name']);
        ListQuery::active($request, $query);

        if ($request->filled('filter.district_id')) {
            $query->where('district_id', $request->integer('filter.district_id'));
        }

        $page = ListQuery::paginate($request, $query, ['name'], 'name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (Tehsil $tehsil) => $this->payload($tehsil))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(Tehsil $tehsil): JsonResponse
    {
        $tehsil->load('district');

        return ApiResponse::success($this->payload($tehsil));
    }

    public function store(SaveTehsilRequest $request): JsonResponse
    {
        $tehsil = $this->catalog->create(
            new Tehsil,
            $request->validated(),
            $request->user(),
            'tehsil',
            'Created tehsil.',
        );
        $tehsil->load('district');

        return ApiResponse::success($this->payload($tehsil), status: 201);
    }

    public function update(SaveTehsilRequest $request, Tehsil $tehsil): JsonResponse
    {
        $tehsil = $this->catalog->update(
            $tehsil,
            $request->validated(),
            $request->user(),
            'tehsil',
            'Updated tehsil.',
        );
        $tehsil->load('district');

        return ApiResponse::success($this->payload($tehsil));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tehsil $tehsil): array
    {
        return [
            'id' => $tehsil->id,
            'district_id' => $tehsil->district_id,
            'district_name' => $tehsil->district?->name,
            'name' => $tehsil->name,
            'is_active' => $tehsil->is_active,
        ];
    }
}
