<?php

namespace App\Repositories\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockTransferSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockTransferRepository implements StockTransferRepositoryInterface
{
    public function paginateTransfers(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = StockTransferSession::query()
            ->where('store_id', $storeId)
            ->with([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'creator:id,name',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $filters['from_warehouse_id']);
        }

        if (! empty($filters['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $filters['to_warehouse_id']);
        }

        if (! empty($filters['date_start'])) {
            $query->whereDate('transfer_date', '>=', $filters['date_start']);
        }

        if (! empty($filters['date_end'])) {
            $query->whereDate('transfer_date', '<=', $filters['date_end']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('transfer_code', 'like', "%{$keyword}%")
                    ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'transfer_date', 'transfer_code'])
            ? $filters['sort_by']
            : 'created_at';

        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) ($filters['limit'] ?? 10), 100);

        return $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function findTransferById(int $storeId, string|int $transferId): ?StockTransferSession
    {
        return StockTransferSession::query()
            ->where('store_id', $storeId)
            ->where('id', $transferId)
            ->first();
    }

    public function findTransferDetail(int $storeId, string|int $transferId): ?StockTransferSession
    {
        return StockTransferSession::query()
            ->where('store_id', $storeId)
            ->where('id', $transferId)
            ->with([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'creator:id,name',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,name',
            ])
            ->first();
    }

    public function createTransferSession(array $data): StockTransferSession
    {
        return StockTransferSession::create($data);
    }

    public function bulkCreateTransferItems(array $items): void
    {
        $now = now();

        $chunks = array_map(fn ($item) => array_merge($item, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $items);

        foreach (array_chunk($chunks, 500) as $chunk) {
            DB::table('stock_transfer_items')->insert($chunk);
        }
    }

    public function countTransfersForDate(int $storeId, string $date): int
    {
        return StockTransferSession::query()
            ->where('store_id', $storeId)
            ->whereDate('created_at', $date)
            ->count();
    }

    public function lockStockBalance(int $storeId, int|string $productId, int|string $warehouseId): ?StockBalance
    {
        return StockBalance::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    public function createStockBalanceIfMissing(int $storeId, int|string $productId, int|string $warehouseId): StockBalance
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

    public function getProductsByIds(int $storeId, array $productIds): Collection
    {
        return Product::query()
            ->where('store_id', $storeId)
            ->whereIn('id', $productIds)
            ->where('status', 'aktif')
            ->whereNull('deleted_at')
            ->get();
    }

    public function getWarehousesByIds(int $storeId, array $warehouseIds): Collection
    {
        return Warehouse::query()
            ->where('store_id', $storeId)
            ->whereIn('id', $warehouseIds)
            ->whereNull('deleted_at')
            ->get();
    }
}
