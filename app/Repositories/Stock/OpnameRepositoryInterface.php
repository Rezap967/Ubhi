<?php

namespace App\Repositories\Stock;

use App\Models\Products\Product;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockOpnameItem;
use App\Models\Stock\StockOpnameSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface OpnameRepositoryInterface
{
    /**
     * Ambil daftar sesi opname dengan filter + pagination.
     *
     * @param  array{page?:int, limit?:int, keyword?:string, status?:string}  $filters
     */
    public function paginateSessions(int $storeId, array $filters): LengthAwarePaginator;

    /**
     * Cari sesi berdasarkan id dan store_id.
     */
    public function findSessionById(int $storeId, int|string $sessionId): ?StockOpnameSession;

    /**
     * Cari sesi dengan lockForUpdate (digunakan saat approve).
     */
    public function findSessionForUpdate(int $storeId, int|string $sessionId): ?StockOpnameSession;

    /**
     * Buat header sesi opname baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSession(array $data): StockOpnameSession;

    /**
     * Bulk insert item opname.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkCreateItems(array $items): void;

    /**
     * Cari item opname dalam sesi tertentu.
     */
    public function findItemInSession(int|string $sessionId, int|string $itemId): ?StockOpnameItem;

    /**
     * Update stok fisik dan selisih pada satu item.
     *
     * @param  array{physical_stock: float, difference: float, updated_by: int}  $data
     */
    public function updateItemPhysicalStock(StockOpnameItem $item, array $data): StockOpnameItem;

    /**
     * Ambil produk sesuai scope sesi — untuk snapshot stok.
     *
     * @param  array{scope_type: string, warehouse_id?: int|null, category_id?: int|null}  $scopeData
     * @return Collection<int, Product>
     */
    public function getProductsForScope(int $storeId, array $scopeData): Collection;

    /**
     * Ambil snapshot stok dari stock_balances untuk daftar produk.
     * Returns map: "product_id:warehouse_id" => quantity  (warehouse_id bisa 'null')
     *
     * @param  array<int>  $productIds
     * @return array<string, float>
     */
    public function getStockSnapshot(int $storeId, array $productIds, ?int $warehouseId): array;

    /**
     * Hitung progress item yang sudah dicek.
     *
     * @return array{checked_items: int, total_items: int, progress_label: string}
     */
    public function countProgress(int|string $sessionId): array;

    /**
     * Ambil semua item dengan physical_stock NOT NULL untuk proses approve.
     *
     * @return Collection<int, StockOpnameItem>
     */
    public function getItemsForApproval(int|string $sessionId): Collection;

    /**
     * Ambil stock_balance dengan lockForUpdate — return null jika belum ada.
     */
    public function lockStockBalance(int $storeId, int|string $productId, ?int $warehouseId): ?StockBalance;

    /**
     * Buat row stock_balance dengan quantity 0 jika belum ada.
     */
    public function createStockBalanceIfMissing(int $storeId, int|string $productId, ?int $warehouseId): StockBalance;

    /**
     * Update quantity dan metadata stock_balance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function updateStockBalance(StockBalance $balance, float $quantity, array $metadata): void;

    /**
     * Update status sesi + info approve.
     *
     * @param  array{status: string, approved_by: int, approved_at: Carbon}  $data
     */
    public function approveSession(StockOpnameSession $session, array $data): StockOpnameSession;

    /**
     * Hitung jumlah sesi pada hari ini untuk generate session_code.
     */
    public function countSessionsToday(int $storeId, string $date): int;
}
