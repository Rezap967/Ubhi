<?php

// app/Http/Controllers/Products/WarehouseController.php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Products\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\Products\Warehouses\WarehouseCollection;
use App\Http\Resources\Products\Warehouses\WarehouseResource;
use App\Models\Products\Warehouse;
use App\Services\Products\Warehouses\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    /**
     * GET /warehouses
     */
    public function index(Request $request): WarehouseCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'in:name,address,warehouse_type'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginated = $this->warehouseService->getList(
            $validated,
            $request->user()
        );

        return new WarehouseCollection($paginated);
    }

    /**
     * GET /warehouses/{warehouse}
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil dimuat.',
            'data' => new WarehouseResource($warehouse),
        ], 200);
    }

    /**
     * POST /warehouses
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create(
            $request->validated(),
            $request->user()
        );

        return (new WarehouseResource($warehouse))
            ->additional([
                'success' => true,
                'message' => 'Gudang berhasil ditambahkan.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /warehouses/{warehouse}
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $this->warehouseService->update($warehouse, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil diperbarui.',
            'data' => new WarehouseResource($warehouse),
        ], 200);
    }

    /**
     * DELETE /warehouses/{warehouse}
     */
    public function destroy(Warehouse $warehouse, Request $request): Response
    {
        $this->authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse, $request->user()->id);

        return response()->noContent();
    }
}
