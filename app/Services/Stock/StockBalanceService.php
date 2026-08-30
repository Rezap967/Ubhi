<?php

namespace App\Services\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use App\Repositories\Stock\StockBalanceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockBalanceService
{
    public function __construct(
        protected StockBalanceRepositoryInterface $repository
    ) {}

    public function validateStoreOwnership(
        int $storeId,
        int $productId,
        ?int $warehouseId
    ): array {
        $product = Product::query()
            ->where('id', $productId)
            ->where('store_id', $storeId)
            ->first();

        if (! $product) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }

        $warehouse = null;

        if ($warehouseId !== null) {
            $warehouse = Warehouse::query()
                ->where('id', $warehouseId)
                ->where('store_id', $storeId)
                ->first();

            if (! $warehouse) {
                throw new InvalidArgumentException('Gudang tidak ditemukan.');
            }
        }

        return [$product, $warehouse];
    }

    /**
     * Ambil stok berdasarkan store.
     */
    public function getCurrentStock(
        int $storeId,
        array $filters = []
    ): Collection {
        return $this->repository->getByStore(
            $storeId,
            $filters
        );
    }

    /**
     * Ambil balance berdasarkan produk dan gudang.
     */
    public function getBalance(
        int $storeId,
        int $productId,
        ?int $warehouseId = null
    ): ?StockBalance {
        $this->validateStoreOwnership(
            $storeId,
            $productId,
            $warehouseId
        );

        return $this->repository->findByProductAndWarehouse(
            $storeId,
            $productId,
            $warehouseId
        );
    }

    /**
     * Ambil balance atau buat jika belum tersedia.
     */
    public function getOrCreateBalance(
        int $storeId,
        int $productId,
        ?int $warehouseId = null
    ): StockBalance {
        $this->validateStoreOwnership(
            $storeId,
            $productId,
            $warehouseId
        );

        return $this->repository->firstOrCreate(
            $storeId,
            $productId,
            $warehouseId
        );
    }

    /**
     * Set quantity stok secara langsung.
     */
    public function setQuantity(
        int $storeId,
        int $productId,
        ?int $warehouseId,
        float $quantity,
        array $metadata = []
    ): StockBalance {
        if ($quantity < 0) {
            throw new InvalidArgumentException(
                'Quantity stok tidak boleh negatif.'
            );
        }

        $this->validateStoreOwnership(
            $storeId,
            $productId,
            $warehouseId
        );

        return DB::transaction(function () use (
            $storeId,
            $productId,
            $warehouseId,
            $quantity,
            $metadata
        ): StockBalance {
            $balance = $this->repository->firstOrCreate(
                $storeId,
                $productId,
                $warehouseId
            );

            $balance = StockBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->repository->updateQuantity(
                $balance,
                $quantity,
                $metadata
            );
        });
    }

    /**
     * Tambahkan stok.
     */
    public function increase(
        int $storeId,
        int $productId,
        ?int $warehouseId,
        float $quantity,
        array $metadata = []
    ): StockBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity penambahan stok harus lebih dari 0.'
            );
        }

        $this->validateStoreOwnership(
            $storeId,
            $productId,
            $warehouseId
        );

        return DB::transaction(function () use (
            $storeId,
            $productId,
            $warehouseId,
            $quantity,
            $metadata
        ): StockBalance {
            $balance = $this->repository->firstOrCreate(
                $storeId,
                $productId,
                $warehouseId
            );

            $balance = StockBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newQuantity = (float) $balance->quantity + $quantity;

            $metadata['last_quantity_change'] = $quantity;

            return $this->repository->updateQuantity(
                $balance,
                $newQuantity,
                $metadata
            );
        });
    }

    /**
     * Kurangi stok.
     */
    public function decrease(
        int $storeId,
        int $productId,
        ?int $warehouseId,
        float $quantity,
        array $metadata = []
    ): StockBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity pengurangan stok harus lebih dari 0.'
            );
        }

        $this->validateStoreOwnership(
            $storeId,
            $productId,
            $warehouseId
        );

        return DB::transaction(function () use (
            $storeId,
            $productId,
            $warehouseId,
            $quantity,
            $metadata
        ): StockBalance {
            $balance = $this->repository->findByProductAndWarehouse(
                $storeId,
                $productId,
                $warehouseId
            );

            if (! $balance) {
                throw new InvalidArgumentException(
                    'Stock balance tidak ditemukan.'
                );
            }

            $balance = StockBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentQuantity = (float) $balance->quantity;

            if ($currentQuantity < $quantity) {
                throw new InvalidArgumentException(
                    'Stok tidak mencukupi.'
                );
            }

            $newQuantity = $currentQuantity - $quantity;

            $metadata['last_quantity_change'] = -$quantity;

            return $this->repository->updateQuantity(
                $balance,
                $newQuantity,
                $metadata
            );
        });
    }

    /**
     * Ambil daftar produk low stock.
     */
    public function getLowStock(int $storeId, array $filters = []): array
    {
        $balances = $this->repository->getLowStock($storeId, $filters);

        return $balances->map(function ($balance) {
            $minimumStock = (float) $balance->product->minimum_stock;
            $quantity = (float) $balance->quantity;

            return [
                'product' => $balance->product->name,
                'sku' => $balance->product->sku,
                'warehouse' => $balance->warehouse?->name ?? 'Stok Umum',
                'quantity' => $quantity,
                'minimum_stock' => $minimumStock,
                'shortage' => max(0, $minimumStock - $quantity),
            ];
        })->values()->toArray();
    }

    /**
     * Cek stok real-time dengan validasi unit_id.
     */
    public function checkStock(int $storeId, int $productId, int $warehouseId, int $unitId): array
    {
        // Validasi produk milik store
        $product = Product::where('id', $productId)
            ->where('store_id', $storeId)
            ->first();

        if (! $product) {
            throw new InvalidArgumentException('Produk tidak ditemukan pada toko ini.');
        }

        // Validasi unit_id harus sama dengan unit default produk
        if ($product->unit_id !== $unitId) {
            throw new InvalidArgumentException('Satuan yang dipilih tidak valid untuk produk ini.');
        }

        // Ambil stok dari repository
        $balance = $this->repository->checkStock($storeId, $productId, $warehouseId);
        $available = $balance ? (float) $balance->quantity : 0;

        return [
            'available' => $available,
            'reserved' => 0, // belum ada sistem reservasi
            'remaining' => $available,
        ];
    }
}
