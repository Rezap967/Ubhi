<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAdditionalFeeRequest;
use App\Http\Requests\Settings\UpdateAdditionalFeeRequest;
use App\Http\Resources\Settings\AdditionalFeeResource;
use App\Services\Settings\AdditionalFeeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdditionalFeeController extends Controller
{
    public function __construct(
        protected AdditionalFeeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $storeId = (int) $request->user()->profile?->store_id;
        $filters = $request->only(['search', 'status', 'fee_type', 'per_page', 'page', 'sort_by', 'sort_dir']);

        $paginator = $this->service->getAll($storeId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Biaya tambahan berhasil diambil',
            'data' => AdditionalFeeResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]
        ], 200);
    }

    public function store(StoreAdditionalFeeRequest $request): JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $storeId = (int) $request->user()->profile?->store_id;

        $fee = $this->service->create($actorId, $storeId, $request->validated(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Biaya tambahan berhasil ditambahkan',
            'data' => new AdditionalFeeResource($fee)
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $storeId = (int) $request->user()->profile?->store_id;
            $fee = $this->service->getDetail($storeId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Detail biaya tambahan berhasil diambil',
                'data' => new AdditionalFeeResource($fee)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya tambahan tidak ditemukan.',
                'errors' => new \stdClass()
            ], 404);
        }
    }

    public function update(UpdateAdditionalFeeRequest $request, int $id): JsonResponse
    {
        try {
            $actorId = (int) $request->user()->id;
            $storeId = (int) $request->user()->profile?->store_id;

            $fee = $this->service->update($actorId, $storeId, $id, $request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Biaya tambahan berhasil diperbarui',
                'data' => new AdditionalFeeResource($fee)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya tambahan tidak ditemukan.',
                'errors' => new \stdClass()
            ], 404);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $actorId = (int) $request->user()->id;
            $storeId = (int) $request->user()->profile?->store_id;

            $this->service->delete($actorId, $storeId, $id, $request);

            return response()->json([
                'success' => true,
                'message' => 'Biaya tambahan berhasil dihapus',
                'data' => null
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya tambahan tidak ditemukan.',
                'errors' => new \stdClass()
            ], 404);
        }
    }
}