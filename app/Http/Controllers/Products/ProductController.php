<?php

// app/Http/Controllers/Products/ProductController.php

namespace App\Http\Controllers\Products;

use App\Exceptions\Products\ProductInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Requests\Products\UpdateProductStatusRequest;
use App\Http\Resources\Products\ProductResource;
use App\Models\Products\Product;
use App\Services\Products\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    // -------------------------------------------------------------------------
    // SF-03 — GET /v1/products
    // -------------------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif,semua'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sort_by' => ['nullable', 'in:name,sell_price,buy_price,status,created_at,sku,initial_stock'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = $this->productService->listProducts(
            $validated,
            $request->user()->role->name,
            $request->user()->profile->store->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk berhasil dimuat.',
            'data' => ProductResource::collection($products)->resolve($request),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // SF-03 — GET /v1/products/{product}
    // -------------------------------------------------------------------------
    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category', 'brand', 'unit', 'children.unit', 'images', 'specialPrices', 'suppliers']);

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil dimuat.',
            'data' => new ProductResource($product),
        ]);
    }

    // -------------------------------------------------------------------------
    // SF-05 — POST /v1/products
    // -------------------------------------------------------------------------
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->createProduct(
            $request->validated(),
            $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data' => new ProductResource($product),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // SF-06 — PUT /v1/products/{product}
    // -------------------------------------------------------------------------
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->updateProduct(
            $product,
            $request->validated(),
            $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data' => new ProductResource($product),
        ]);
    }

    // -------------------------------------------------------------------------
    // SF-07 — PATCH /v1/products/{product}/status
    // -------------------------------------------------------------------------
    public function updateStatus(UpdateProductStatusRequest $request, Product $product): JsonResponse
    {
        $this->authorize('updateStatus', $product);

        $product = $this->productService->updateStatus(
            $product,
            $request->validated('status'),
            $request->user()->id,
        );

        $label = $product->status === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'success' => true,
            'message' => "Produk berhasil {$label}.",
            'data' => new ProductResource($product),
        ]);
    }

    // -------------------------------------------------------------------------
    // SF-07 — GET /v1/products/{product}/check-history
    // Digunakan frontend untuk menentukan dialog hapus: permanen atau nonaktifkan.
    // -------------------------------------------------------------------------
    public function checkHistory(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $hasHistory = $this->productService->hasTransactionHistory($product);

        return response()->json([
            'success' => true,
            'data' => [
                'ada_histori' => $hasHistory,
                'product_id' => $product->id,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // SF-07 — DELETE /v1/products/{product}
    // -------------------------------------------------------------------------
    public function destroy(Request $request, Product $product): JsonResponse|Response
    {
        $this->authorize('delete', $product);

        try {
            $this->productService->deleteProduct($product, $request->user()->id);
        } catch (ProductInUseException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'ERR-PRODUCT-IN-USE-01',
            ], 409);
        }

        return response()->noContent();
    }
}
