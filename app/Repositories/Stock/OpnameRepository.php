<?php

namespace App\Repositories\Stock;

use App\Models\Products\Product;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockOpnameItem;
use App\Models\Stock\StockOpnameSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OpnameRepository implements OpnameRepositoryInterface
{
    public function paginateSessions(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = StockOpnameSession::query()
            ->where('store_id', $storeId)
            ->with(['creator:id,name', 'approver:id,name', 'warehouse:id,name', 'category:id,name'])
            ->withCount([
                'items as total_items',
                'items as checked_items' => fn ($q) => $q->whereNotNull('physical_stock'),
            ]);

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('session_name', 'like', "%{$keyword}%")
                    ->orWhere('session_code', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'session_name', 'status'])
            ? $filters['sort_by']
            : 'created_at';

        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) ($filters['limit'] ?? 10), 100);

        return $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function findSessionById(int $storeId, int|string $sessionId): ?StockOpnameSession
    {
        return StockOpnameSession::query()
            ->where('store_id', $storeId)
            ->where('id', $sessionId)
            ->first();
    }

    public function findSessionForUpdate(int $storeId, int|string $sessionId): ?StockOpnameSession
    {
        return StockOpnameSession::query()
            ->where('store_id', $storeId)
            ->where('id', $sessionId)
            ->lockForUpdate()
            ->first();
    }

    public function createSession(array $data): StockOpnameSession
    {
        return StockOpnameSession::create($data);
    }

    public function bulkCreateItems(array $items): void
    {
        $now = now();

        $chunks = array_map(fn ($item) => array_merge($item, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $items);

        // Insert dalam chunk 500 agar tidak overflow
        foreach (array_chunk($chunks, 500) as $chunk) {
            DB::table('stock_opname_items')->insert($chunk);
        }
    }

    public function findItemInSession(int|string $sessionId, int|string $itemId): ?StockOpnameItem
    {
        return StockOpnameItem::query()
            ->where('session_id', $sessionId)
            ->where('id', $itemId)
            ->first();
    }

    public function updateItemPhysicalStock(StockOpnameItem $item, array $data): StockOpnameItem
    {
        $item->physical_stock = $data['physical_stock'];
        $item->difference = $data['difference'];
        $item->updated_by = $data['updated_by'];
        $item->save();

        return $item->fresh(['product:id,name,sku', 'warehouse:id,name']);
    }

    public function getProductsForScope(int $storeId, array $scopeData): Collection
    {
        $query = Product::query()
            ->where('store_id', $storeId)
            ->where('status', 'aktif')
            ->whereNull('deleted_at')
            ->select('id', 'name', 'sku', 'category_id');

        if ($scopeData['scope_type'] === 'per_category' && ! empty($scopeData['category_id'])) {
            $query->where('category_id', $scopeData['category_id']);
        }

        if ($scopeData['scope_type'] === 'per_warehouse' && ! empty($scopeData['warehouse_id'])) {
            // Produk yang punya stok di gudang tersebut (termasuk yang belum punya row = stok 0)
            // Ambil semua produk aktif + join ke stock_balances jika ada
            // Pendekatan: ambil semua produk aktif milik store (warehouse sudah di-scope di item)
            // biarkan nanti item dibuat dengan system_stock = stok di warehouse itu
        }

        return $query->get();
    }

    public function getStockSnapshot(int $storeId, array $productIds, ?int $warehouseId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = StockBalance::query()
            ->where('store_id', $storeId)
            ->whereIn('product_id', $productIds);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            // all_products: ambil semua balances untuk produk tersebut
            // Nanti di service kita map per product+warehouse
        }

        $balances = $query->get(['product_id', 'warehouse_id', 'quantity']);

        $map = [];
        foreach ($balances as $balance) {
            $key = $balance->product_id.':'.($balance->warehouse_id ?? 'null');
            $map[$key] = (float) $balance->quantity;
        }

        return $map;
    }

    public function countProgress(int|string $sessionId): array
    {
        $total = StockOpnameItem::where('session_id', $sessionId)->count();
        $checked = StockOpnameItem::where('session_id', $sessionId)
            ->whereNotNull('physical_stock')
            ->count();

        return [
            'checked_items' => $checked,
            'total_items' => $total,
            'progress_label' => "{$checked}/{$total}",
        ];
    }

    public function getItemsForApproval(int|string $sessionId): Collection
    {
        return StockOpnameItem::query()
            ->where('session_id', $sessionId)
            ->whereNotNull('physical_stock')
            ->get();
    }

    public function lockStockBalance(int $storeId, int|string $productId, ?int $warehouseId): ?StockBalance
    {
        return StockBalance::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->when(
                $warehouseId === null,
                fn ($q) => $q->whereNull('warehouse_id'),
                fn ($q) => $q->where('warehouse_id', $warehouseId)
            )
            ->lockForUpdate()
            ->first();
    }

    public function createStockBalanceIfMissing(int $storeId, int|string $productId, ?int $warehouseId): StockBalance
    {
        return StockBalance::firstOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            ['quantity' => 0]
        );
    }

    public function updateStockBalance(StockBalance $balance, float $quantity, array $metadata): void
    {
        $balance->quantity = $quantity;

        foreach (['last_movement_type', 'last_reference_no', 'last_quantity_change', 'last_changed_by', 'last_changed_at'] as $field) {
            if (array_key_exists($field, $metadata)) {
                $balance->{$field} = $metadata[$field];
            }
        }

        $balance->save();
    }

    public function approveSession(StockOpnameSession $session, array $data): StockOpnameSession
    {
        $session->status = $data['status'];
        $session->approved_by = $data['approved_by'];
        $session->approved_at = $data['approved_at'];
        $session->save();

        return $session->fresh(['creator:id,name', 'approver:id,name']);
    }

    public function countSessionsToday(int $storeId, string $date): int
    {
        return StockOpnameSession::query()
            ->where('store_id', $storeId)
            ->whereDate('created_at', $date)
            ->count();
    }
}
