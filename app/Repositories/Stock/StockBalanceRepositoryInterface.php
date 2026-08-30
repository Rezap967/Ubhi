<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockBalance;
use Illuminate\Database\Eloquent\Collection;

interface StockBalanceRepositoryInterface
{
    /**
     * Ambil stok berdasarkan store.
     */
    public function getByStore(
        int $storeId,
        array $filters = []
    ): Collection;

    /**
     * Ambil satu balance berdasarkan produk dan gudang.
     */
    public function findByProductAndWarehouse(
        int $storeId,
        int $productId,
        ?int $warehouseId
    ): ?StockBalance;

    /**
     * Buat atau ambil balance stok.
     */
    public function firstOrCreate(
        int $storeId,
        int $productId,
        ?int $warehouseId
    ): StockBalance;

    /**
     * Update quantity stok.
     */
    public function updateQuantity(
        StockBalance $balance,
        float $quantity,
        array $metadata = []
    ): StockBalance;

    public function getLowStock(int $storeId, array $filters = []): Collection;

    public function checkStock(int $storeId, int $productId, int $warehouseId): ?StockBalance;
}
