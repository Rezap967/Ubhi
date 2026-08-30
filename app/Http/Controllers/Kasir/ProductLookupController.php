<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\PosCategoryRequest;
use App\Http\Requests\Kasir\PosProductSearchRequest;
use App\Http\Requests\Kasir\TopSellingProductRequest;
use App\Http\Resources\Kasir\PosCategoryResource;
use App\Http\Resources\Kasir\PosProductResource;
use App\Http\Resources\Kasir\TopSellingProductResource;
use App\Services\Kasir\ProductLookupService;
use Illuminate\Http\JsonResponse;

class ProductLookupController extends Controller
{
    public function __construct(
        protected ProductLookupService $productLookupService
    ) {}

    /**
     * GET /api/v1/pos/products
     */
    public function index(PosProductSearchRequest $request): JsonResponse
    {
        $paginator = $this->productLookupService->searchProducts(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Produk POS berhasil dimuat.',
            'data' => PosProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/pos/categories
     */
    public function categories(PosCategoryRequest $request): JsonResponse
    {
        $includeAll = $request->input('include_all', true);
        // Cast to boolean if passed as string 'false' / 'true'
        if (is_string($includeAll)) {
            $includeAll = filter_var($includeAll, FILTER_VALIDATE_BOOLEAN);
        }

        $categories = $this->productLookupService->getCategories(
            $request->user(),
            $includeAll
        );

        return response()->json([
            'success' => true,
            'message' => 'Kategori POS berhasil dimuat.',
            'data' => PosCategoryResource::collection($categories),
        ]);
    }

    /**
     * GET /api/v1/pos/products/top-selling
     */
    public function topSelling(TopSellingProductRequest $request): JsonResponse
    {
        $topSelling = $this->productLookupService->getTopSellingProducts(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Produk terlaris berhasil dimuat.',
            'data' => TopSellingProductResource::collection($topSelling),
        ]);
    }
}
