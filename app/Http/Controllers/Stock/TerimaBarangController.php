<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreReceiptRequest;
use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Services\Stock\ReceiptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TerimaBarangController extends Controller
{
    public function __construct(protected ReceiptService $receiptService) {}

    public function index(Request $request): Response
    {
        $storeId = $this->resolveStoreId($request);

        $filters = $request->only(['keyword']);

        // NOTE: pastikan method getAll() ada di ReceiptService.
        // Kalau logic query ada di repository terpisah, inject repository-nya
        // juga di constructor, jangan panggil property yang tidak dideklarasikan.
        $receipts = $this->receiptService->getAll($storeId, $filters);

        $products = Product::where('store_id', $storeId)
            ->where('status', 'aktif')
            ->select('id', 'name', 'unit_id')
            ->get();

        $warehouses = Warehouse::where('store_id', $storeId)
            ->where('status', 'aktif')
            ->select('id', 'name')
            ->get();

        return Inertia::render('StokBarang/TerimaBarang/Index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'products' => $products,
            'warehouses' => $warehouses,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);
        $filters = $request->only(['keyword']);

        $receipts = $this->receiptService->getAll($storeId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Daftar penerimaan barang berhasil dimuat.',
            'data' => $receipts,
        ]);
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        try {
            $receipt = $this->receiptService->store(
                $storeId,
                $request->user()->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Penerimaan barang berhasil disimpan.',
                'data' => $receipt,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        try {
            $this->receiptService->delete($storeId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Penerimaan barang berhasil dihapus.',
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }
    }

    private function resolveStoreId(Request $request): int
    {
        $storeId = $request->user()->profile?->store_id;

        if (! $storeId) {
            abort(403, 'User tidak memiliki toko.');
        }

        return $storeId;
    }
}
