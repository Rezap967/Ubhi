<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokMasterDataController extends Controller
{
    /**
     * GET /api/stok/master-data?context=opname
     *
     * Mengembalikan data master (gudang, kategori) sesuai context.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);

        $context = $request->query('context', 'general');

        if ($context === 'opname') {
            $warehouses = Warehouse::query()
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($w) => ['id' => (string) $w->id, 'name' => $w->name])
                ->values();

            $categories = Category::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name])
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouses' => $warehouses,
                    'categories' => $categories,
                ],
            ]);
        }

        if ($context === 'transfer') {
            $warehouses = Warehouse::query()
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($w) => ['id' => (string) $w->id, 'name' => $w->name])
                ->values();

            $stockMap = StockBalance::query()
                ->where('store_id', $storeId)
                ->selectRaw('product_id, SUM(quantity) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            $products = Product::query()
                ->where('store_id', $storeId)
                ->where('status', 'aktif')
                ->whereNull('deleted_at')
                ->with(['unit:id,name'])
                ->select('id', 'sku', 'name', 'unit_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'unit_name' => $p->unit?->name,
                    'available_stock' => (float) ($stockMap->get($p->id) ?? 0),
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouses' => $warehouses,
                    'products' => $products,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }

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
