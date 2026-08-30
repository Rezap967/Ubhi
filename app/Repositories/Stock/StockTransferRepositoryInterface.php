<?php

namespace App\Repositories\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockTransferSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface StockTransferRepositoryInterface
{
    /**
     * Ambil daftar transfer stok dengan filter dan pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateTransfers(int $storeId, array $filters): LengthAwarePaginator;

    /**
     * Cari transfer stok berdasarkan id dan store_id.
     */
    public function findTransferById(int $storeId, string|int $transferId): ?StockTransferSession;

    /**
     * Cari detail transfer stok beserta relasi items, products, warehouses, creator.
     */
    public function findTransferDetail(int $storeId, string|int $transferId): ?StockTransferSession;

    /**
     * Buat header transfer stok.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTransferSession(array $data): StockTransferSession;

    /**
     * Bulk insert item transfer stok.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkCreateTransferItems(array $items): void;

    /**
     * Hitung transfer hari ini untuk generate transfer_code.
     */
    public function countTransfersForDate(int $storeId, string $date): int;

    /**
     * Lock row stock_balance dengan lockForUpdate.
     */
    public function lockStockBalance(int $storeId, int|string $productId, int|string $warehouseId): ?StockBalance;

    /**
     * Buat row stock_balance jika belum ada (quantity = 0).
     */
    public function createStockBalanceIfMissing(int $storeId, int|string $productId, int|string $warehouseId): StockBalance;

    /**
     * Update quantity dan metadata stock_balance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function updateStockBalance(StockBalance $balance, float $quantity, array $metadata): void;

    /**
     * Ambil produk aktif milik store berdasarkan IDs.
     *
     * @param  array<int>  $productIds
     * @return Collection<int, Product>
     */
    public function getProductsByIds(int $storeId, array $productIds): Collection;

    /**
     * Ambil gudang aktif milik store berdasarkan IDs.
     *
     * @param  array<int>  $warehouseIds
     * @return Collection<int, Warehouse>
     */
    public function getWarehousesByIds(int $storeId, array $warehouseIds): Collection;
}
