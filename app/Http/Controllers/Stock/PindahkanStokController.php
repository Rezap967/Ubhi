<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\CancelStockTransferRequest;
use App\Http\Requests\Stock\SendStockTransferRequest;
use App\Http\Requests\Stock\StoreStockTransferRequest;
use App\Models\Stock\StockTransferSession;
use App\Services\Stock\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PindahkanStokController extends Controller
{
    public function __construct(
        protected StockTransferService $stockTransferService
    ) {}

    /**
     * GET /api/stok/pindah
     * Daftar transfer stok dengan filter dan pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransferSession::class);

        $storeId = $this->resolveStoreId($request);

        $filters = $request->only([
            'page',
            'limit',
            'status',
            'keyword',
            'date_start',
            'date_end',
            'from_warehouse_id',
            'to_warehouse_id',
            'sort_by',
            'sort_order',
        ]);

        $result = $this->stockTransferService->getList($storeId, $filters);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/stok/pindah
     * Buat draft transfer stok baru (belum mempengaruhi stok).
     */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $this->authorize('create', StockTransferSession::class);

        $storeId = $this->resolveStoreId($request);

        $result = $this->stockTransferService->createTransfer(
            storeId: $storeId,
            userId: (int) $request->user()->id,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft transfer stok berhasil dibuat.',
            'data' => $result,
        ], 201);
    }

    /**
     * GET /api/stok/pindah/{id}
     * Detail transaksi transfer stok.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        $transfer = StockTransferSession::where('store_id', $storeId)->findOrFail($id);

        $this->authorize('view', $transfer);

        $result = $this->stockTransferService->getDetail($storeId, $id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/stok/pindah/{id}/send
     * Konfirmasi pengiriman: draft → selesai, mutasikan stok.
     */
    public function send(SendStockTransferRequest $request, string $id): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        $transfer = StockTransferSession::where('store_id', $storeId)->findOrFail($id);

        $this->authorize('create', $transfer);

        $result = $this->stockTransferService->sendTransfer(
            storeId: $storeId,
            transferId: $id,
            userId: (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer stok berhasil dikirim dan stok telah diperbarui.',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/stok/pindah/{id}/cancel
     * Batalkan transfer. Jika sudah selesai, reversal stok dilakukan.
     */
    public function cancel(CancelStockTransferRequest $request, string $id): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        $transfer = StockTransferSession::where('store_id', $storeId)->findOrFail($id);

        $this->authorize('view', $transfer);

        $result = $this->stockTransferService->cancelTransfer(
            storeId: $storeId,
            transferId: $id,
            userId: (int) $request->user()->id,
            reason: $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer stok berhasil dibatalkan.',
            'data' => $result,
        ]);
    }

    /**
     * Resolve store_id user secara aman.
     */
    private function resolveStoreId(Request $request): int
    {
        try {
            return $request->user()->activeStoreId();
        } catch (\RuntimeException) {
            abort(response()->json([
                'success' => false,
                'message' => 'User belum memiliki store aktif.',
                'errors' => (object) [],
            ], 403));
        }
    }
}
