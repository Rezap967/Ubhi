<?php

// app/Http/Controllers/Product/BrandController.php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\Brands\StoreBrandRequest;
use App\Http\Requests\Products\Brands\UpdateBrandRequest;
use App\Http\Requests\Products\Brands\UpdateBrandStatusRequest;
use App\Http\Resources\Products\Brands\BrandCollection;
use App\Http\Resources\Products\Brands\BrandResource;
use App\Models\Products\Brand;
use App\Services\Products\Brands\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService
    ) {}

    public function index(Request $request): BrandCollection
    {
        $this->authorize('viewAny', Brand::class);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort_by' => ['nullable', 'in:name,description,status'], // 'product_count' dihapus sementara dari allow-list
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $keyword = $validated['keyword'] ?? null;
        $status = $validated['status'] ?? 'all';
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $limit = $validated['limit'] ?? 15;

        $brands = Brand::query()
            ->when($keyword, function ($q) use ($keyword) {
                $kw = mb_strtolower($keyword);
                $q->where(function ($sub) use ($kw) {
                    $sub->whereRaw('LOWER(name) LIKE ?', ["%{$kw}%"])
                        ->orWhereRaw('LOWER(description) LIKE ?', ["%{$kw}%"]);
                });
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->whereNull('deleted_at')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($limit);

        return new BrandCollection($brands);
    }

    public function show(Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        return response()->json([
            'success' => true,
            'message' => 'Detail merek berhasil dimuat.',
            'data' => new BrandResource($brand),
        ], 200);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $this->authorize('create', Brand::class);

        $brand = $this->brandService->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Merek berhasil ditambahkan.',
            'data' => new BrandResource($brand),
        ], 201);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $brand = $this->brandService->update($brand, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Merek berhasil diperbarui.',
            'data' => new BrandResource($brand),
        ], 200);
    }

    public function updateStatus(UpdateBrandStatusRequest $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $brand = $this->brandService->updateStatus($brand, $request->validated('status'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Status merek berhasil diperbarui.',
            'data' => new BrandResource($brand),
        ], 200);
    }

    public function destroy(Request $request, Brand $brand): Response
    {
        $this->authorize('delete', $brand);

        $this->brandService->delete($brand, $request->user()->id);

        return response()->noContent();
    }
}
