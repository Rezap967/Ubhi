<?php

namespace App\Services\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockMovement;
use App\Repositories\Stock\StockBalanceRepositoryInterface;
use InvalidArgumentException;

class StockMovementService
{
    public function __construct(
        protected StockBalanceRepositoryInterface $stockBalanceRepository
    ) {}

    /**
     * Catat penerimaan barang dari Purchase Order ke stok berjalan dan buat riwayat movement.
     */
    public function recordReceipt(
        int $storeId,
        int $productId,
        ?int $warehouseId,
        float $quantity,
        int $poId,
        int $poItemId,
        ?int $userId = null,
        ?string $notes = null
    ): StockBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah barang diterima harus lebih dari 0.');
        }

        // Validasi kepemilikan store untuk produk
        $product = Product::query()
            ->where('id', $productId)
            ->where('store_id', $storeId)
            ->first();

        if (! $product) {
            throw new InvalidArgumentException('Produk tidak ditemukan pada toko ini.');
        }

        // Validasi gudang jika diberikan
        if ($warehouseId !== null) {
            $warehouse = Warehouse::query()
                ->where('id', $warehouseId)
                ->where('store_id', $storeId)
                ->first();

            if (! $warehouse) {
                throw new InvalidArgumentException('Gudang tidak ditemukan pada toko ini.');
            }
        }

        // Pastikan baris stock_balance ada lalu kunci dengan lockForUpdate
        $balance = $this->stockBalanceRepository->firstOrCreate(
            $storeId,
            $productId,
            $warehouseId
        );

        $balance = StockBalance::query()
            ->whereKey($balance->id)
            ->lockForUpdate()
            ->firstOrFail();

        $beforeQuantity = (float) $balance->quantity;
        $afterQuantity = $beforeQuantity + $quantity;

        // Update saldo stok berjalan
        $updatedBalance = $this->stockBalanceRepository->updateQuantity(
            $balance,
            $afterQuantity,
            [
                'last_movement_type' => 'purchase_order_receipt',
                'last_reference_no' => (string) $poId,
                'last_quantity_change' => $quantity,
                'last_changed_by' => $userId,
                'last_changed_at' => now(),
            ]
        );

        // Catat ke log audit pergerakan stok (stock_movements)
        StockMovement::create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'reference_type' => 'purchase_order_receipt',
            'reference_id' => $poId,
            'movement_type' => 'in',
            'quantity' => $quantity,
            'before_quantity' => $beforeQuantity,
            'after_quantity' => $afterQuantity,
            'notes' => $notes ?? "Penerimaan PO #{$poId} (Item #{$poItemId})",
            'movement_at' => now(),
            'created_by' => $userId,
        ]);

        return $updatedBalance;
    }
}
