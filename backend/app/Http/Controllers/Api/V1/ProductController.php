<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Catalog\SaveProductRequest;
use App\Models\Product;
use App\Services\Catalog\CatalogWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function __construct(private CatalogWriter $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $query = Product::query();
        ListQuery::search($request, $query, ['generic_name', 'concentration', 'formulation']);
        ListQuery::active($request, $query);

        if ($request->filled('filter.category')) {
            $query->where('category', $request->string('filter.category')->value());
        }

        $page = ListQuery::paginate($request, $query, ['generic_name', 'formulation', 'category'], 'generic_name');

        return ApiResponse::success(
            $page->getCollection()->map(fn (Product $product) => $this->payload($product))->values(),
            ListQuery::meta($page),
        );
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success($this->payload($product));
    }

    public function store(SaveProductRequest $request): JsonResponse
    {
        $product = $this->catalog->create(
            new Product,
            $request->validated(),
            $request->user(),
            'product',
            'Created product.',
        );

        return ApiResponse::success($this->payload($product), status: 201);
    }

    public function update(SaveProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->catalog->update(
            $product,
            $request->validated(),
            $request->user(),
            'product',
            'Updated product.',
        );

        return ApiResponse::success($this->payload($product));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product): array
    {
        return [
            'id' => $product->id,
            'generic_name' => $product->generic_name,
            'concentration' => $product->concentration,
            'formulation' => $product->formulation,
            'category' => $product->category,
            'is_restricted' => $product->is_restricted,
            'is_active' => $product->is_active,
        ];
    }
}
