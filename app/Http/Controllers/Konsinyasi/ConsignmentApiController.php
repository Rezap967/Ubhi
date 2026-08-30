<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Controller;
use App\Models\Konsinyasi\ConsignmentItem;
use App\Models\Products\Product;
use App\Models\Products\Supplier;
use App\Models\Products\Warehouse;
use App\Services\Konsinyasi\ConsignmentItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsignmentApiController extends Controller
{
    public function __construct(
        protected ConsignmentItemService $itemService,
    ) {
    }

    /**
     * Mengambil store_id dari profil user secara aman.
     */
    protected function getStoreId(Request $request): ?int
    {
        $user = $request->user();

        if ($user && $user->profile?->store_id) {
            return (int) $user->profile->store_id;
        }

        return null;
    }

    /**
     * Response penolakan jika store_id tidak ditemukan.
     */
    protected function unassignedStoreResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Store aktif tidak ditemukan pada profil user.',
            'data' => null,
        ], 422);
    }

    /**
     * GET /api/v1/consignments/master-data
     */
    public function masterData(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $suppliers = Supplier::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->where('store_id', $storeId)
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('store_id', $storeId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Master data konsinyasi berhasil diambil.',
            'data' => [
                'suppliers' => $suppliers,
                'products' => $products,
                'warehouses' => $warehouses,
                'statuses' => ['active', 'inactive', 'closed'],
                'commission_types' => ['margin', 'percentage', 'fixed'],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/consignments/items-by-supplier/{supplier}
     */
    public function itemsBySupplier(Request $request, int $supplier): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $exists = Supplier::query()->where('id', $supplier)->exists();

        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $items = $this->itemService->getBySupplier($storeId, $supplier);

        return response()->json([
            'success' => true,
            'message' => 'Data barang konsinyasi supplier berhasil diambil.',
            'data' => $items->map(fn (ConsignmentItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'selling_price' => (float) $item->selling_price,
                'consignment_price' => (float) $item->consignment_price,
            ]),
        ], 200);
    }

    /**
     * GET /api/v1/consignments/items/{item}/stock
     */
    public function itemStock(Request $request, int $item): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $exists = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->where('id', $item)
            ->exists();

        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $stock = $this->itemService->getStockRealtime($storeId, $item, $warehouseId);

        if ($stock === null) {
            return response()->json([
                'success' => true,
                'message' => 'Data stok realtime barang konsinyasi berhasil diambil.',
                'data' => [
                    'quantity_available' => 0,
                    'quantity_sold' => 0,
                    'quantity_returned' => 0,
                    'last_movement_type' => null,
                    'last_changed_at' => null,
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data stok realtime barang konsinyasi berhasil diambil.',
            'data' => $stock,
        ], 200);
    }
}