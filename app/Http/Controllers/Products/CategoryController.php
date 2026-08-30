<?php

// app/Http/Controllers/Product/CategoryController.php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\Categories\StoreCategoryRequest;
use App\Http\Requests\Products\Categories\UpdateCategoryRequest;
use App\Http\Requests\Products\Categories\UpdateCategoryStatusRequest;
use App\Http\Resources\Products\Categories\CategoryCollection;
use App\Http\Resources\Products\Categories\CategoryResource;
use App\Models\Products\Category;
use App\Services\Products\Categories\CategoryService;
use App\Services\Products\Categories\CategoryValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService,
        protected CategoryValidationService $categoryValidationService
    ) {}

    public function index(Request $request): CategoryCollection
    {
        $this->authorize('viewAny', Category::class);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort_by' => ['nullable', 'in:name,status,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $keyword = $validated['keyword'] ?? null;
        $status = $validated['status'] ?? 'all';
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $limit = $validated['limit'] ?? 15;

        $categories = Category::query()
            ->when($keyword, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($keyword).'%']))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->whereNull('deleted_at')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($limit);

        return new CategoryCollection($categories);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil dimuat.',
            'data' => new CategoryResource($category),
        ], 200);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->createCategory($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->updateCategory($category, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui.',
            'data' => new CategoryResource($category),
        ], 200);
    }

    public function updateStatus(UpdateCategoryStatusRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->updateStatus($category, $request->validated('status'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Status kategori berhasil diperbarui.',
            'data' => new CategoryResource($category),
        ], 200);
    }

    public function destroy(Request $request, Category $category): Response
    {
        $this->authorize('delete', $category);

        // Re-validasi di backend — jangan percaya FE sudah panggil validate-delete
        $this->categoryService->softDelete($category, $request->user()->id);

        return response()->noContent();
    }
}
