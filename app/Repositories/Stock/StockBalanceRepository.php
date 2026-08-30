<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockBalance;
use Illuminate\Database\Eloquent\Collection;

class StockBalanceRepository implements StockBalanceRepositoryInterface
{
    public function getByStore(
        int $storeId,
        array $filters = []
    ): Collection {
        $query = StockBalance::query()
            ->where('store_id', $storeId)
            ->with([
                'product',
                'product.category',
                'product.unit',
                'warehouse',
                'lastChangedBy',
            ]);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (array_key_exists('warehouse_id', $filters)) {
            if ($filters['warehouse_id'] === null) {
                $query->whereNull('warehouse_id');
            } else {
                $query->where('warehouse_id', $filters['warehouse_id']);
            }
        }

        return $query
            ->orderByDesc('quantity')
            ->get();
    }

    public function findByProductAndWarehouse(
        int $storeId,
        int $productId,
        ?int $warehouseId
    ): ?StockBalance {
        return StockBalance::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->when(
                $warehouseId === null,
                fn ($query) => $query->whereNull('warehouse_id'),
                fn ($query) => $query->where('warehouse_id', $warehouseId)
            )
            ->first();
    }

    public function firstOrCreate(
        int $storeId,
        int $productId,
        ?int $warehouseId
    ): StockBalance {
        return StockBalance::firstOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'quantity' => 0,
            ]
        );
    }

    public function updateQuantity(
        StockBalance $balance,
        float $quantity,
        array $metadata = []
    ): StockBalance {
        $balance->quantity = $quantity;

        if (array_key_exists('last_movement_type', $metadata)) {
            $balance->last_movement_type = $metadata['last_movement_type'];
        }

        if (array_key_exists('last_reference_no', $metadata)) {
            $balance->last_reference_no = $metadata['last_reference_no'];
        }

        if (array_key_exists('last_quantity_change', $metadata)) {
            $balance->last_quantity_change = $metadata['last_quantity_change'];
        }

        if (array_key_exists('last_changed_by', $metadata)) {
            $balance->last_changed_by = $metadata['last_changed_by'];
        }

        if (array_key_exists('last_changed_at', $metadata)) {
            $balance->last_changed_at = $metadata['last_changed_at'];
        }

        $balance->save();

        return $balance->fresh([
            'product',
            'warehouse',
            'lastChangedBy',
        ]);
    }

    /**
     * Ambil daftar produk dengan stok di bawah minimum.
     */
    public function getLowStock(int $storeId, array $filters = []): Collection
    {
        $query = StockBalance::query()
            ->join('products', 'stock_balances.product_id', '=', 'products.id')
            ->where('stock_balances.store_id', $storeId)
            ->whereNotNull('products.minimum_stock')
            ->where('products.minimum_stock', '>', 0)
            ->whereColumn('stock_balances.quantity', '<=', 'products.minimum_stock')
            ->select('stock_balances.*') // Hindari konflik kolom
            ->with([
                'product',
                'warehouse',
            ]);

        if (! empty($filters['warehouse_id'])) {
            $query->where('stock_balances.warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('products.brand_id', $filters['brand_id']);
        }

        return $query->get();
    }

    /**
     * Cek stok untuk produk dan gudang tertentu.
     */
    public function checkStock(int $storeId, int $productId, int $warehouseId): ?StockBalance
    {
        return StockBalance::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }
}
