<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ApproveOpnameSessionRequest;
use App\Http\Requests\Stock\StoreOpnameSessionRequest;
use App\Http\Requests\Stock\UpdateOpnameItemRequest;
use App\Models\Stock\StockOpnameSession;
use App\Services\Stock\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokOpnameController extends Controller
{
    public function __construct(
        protected OpnameService $opnameService
    ) {}

    /**
     * GET /api/stok/opname
     * Daftar sesi opname dengan filter dan pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockOpnameSession::class);

        $storeId = $this->resolveStoreId($request);

        $filters = $request->only(['page', 'limit', 'keyword', 'status', 'sort_by', 'sort_order']);

        $result = $this->opnameService->getList($storeId, $filters);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/stok/opname
     * Buat sesi opname baru beserta snapshot stok.
     */
    public function store(StoreOpnameSessionRequest $request): JsonResponse
    {
        $this->authorize('create', StockOpnameSession::class);

        $storeId = $this->resolveStoreId($request);

        $result = $this->opnameService->createSession(
            storeId: $storeId,
            userId: (int) $request->user()->id,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Sesi opname berhasil dibuat.',
            'data' => $result,
        ], 201);
    }

    /**
     * GET /api/stok/opname/{id}
     * Detail sesi opname beserta semua item.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        $result = $this->opnameService->getDetail($storeId, $id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * PATCH /api/stok/opname/{id}/items/{itemId}
     * Update stok fisik satu item.
     */
    public function updateItem(string $id, string $itemId, UpdateOpnameItemRequest $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        // Load session untuk policy check
        $session = StockOpnameSession::where('store_id', $storeId)
            ->findOrFail($id);

        $this->authorize('updateItem', $session);

        $result = $this->opnameService->updateItem(
            storeId: $storeId,
            userId: (int) $request->user()->id,
            sessionId: $id,
            itemId: $itemId,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stok fisik berhasil disimpan.',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/stok/opname/{id}/approve
     * Approve sesi dan adjust stock_balances.
     */
    public function approve(string $id, ApproveOpnameSessionRequest $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        // Load session untuk policy check
        $session = StockOpnameSession::where('store_id', $storeId)
            ->findOrFail($id);

        $this->authorize('approve', $session);

        $result = $this->opnameService->approve(
            storeId: $storeId,
            userId: (int) $request->user()->id,
            sessionId: $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Sesi opname berhasil diselesaikan. Stok telah disesuaikan.',
            'data' => $result,
        ]);
    }

    /**
     * Resolve store_id dari user secara aman.
     * Abort 403 jika user belum punya store aktif.
     */
    private function resolveStoreId(Request $request): int
    {
        try {
            return $request->user()->activeStoreId();
        } catch (\RuntimeException $e) {
            abort(response()->json([
                'success' => false,
                'message' => 'User belum memiliki store aktif.',
                'errors' => (object) [],
            ], 403));
        }
    }
}
