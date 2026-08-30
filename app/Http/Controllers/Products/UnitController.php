<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreUnitRequest;
use App\Http\Requests\Products\UpdateUnitRequest;
use App\Http\Requests\Products\UpdateUnitStatusRequest;
use App\Http\Resources\Products\UnitCollection;
use App\Http\Resources\Products\UnitResource;
use App\Models\Products\ProductUnit;
use App\Repositories\Products\UnitRepository;
use App\Services\Products\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService,
        protected UnitRepository $unitRepository
    ) {}

    public function index(Request $request): UnitCollection
    {
        $this->authorize('viewAny', ProductUnit::class);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort_by' => ['nullable', 'in:name,product_count,status'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $units = $this->unitService->getList($validated);

        return new UnitCollection($units);
    }

    public function show(int $id): JsonResponse
    {
        $unit = $this->unitRepository->findById($id);

        $this->authorize('view', $unit);

        return response()->json([
            'success' => true,
            'message' => 'Detail satuan berhasil dimuat.',
            'data' => new UnitResource($unit),
        ], 200);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $this->authorize('create', ProductUnit::class);

        $unit = $this->unitService->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil dibuat.',
            'data' => new UnitResource($unit),
        ], 201);
    }

    public function update(UpdateUnitRequest $request, ProductUnit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        $unit = $this->unitService->update($unit, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil diperbarui.',
            'data' => new UnitResource($unit),
        ], 200);
    }

    public function updateStatus(UpdateUnitStatusRequest $request, ProductUnit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        $unit = $this->unitService->updateStatus($unit, $request->validated('status'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Status satuan berhasil diperbarui.',
            'data' => new UnitResource($unit),
        ], 200);
    }

    public function destroy(Request $request, ProductUnit $unit): Response
    {
        $this->authorize('delete', $unit);

        $this->unitService->delete($unit, $request->user()->id);

        return response()->noContent();
    }
}
