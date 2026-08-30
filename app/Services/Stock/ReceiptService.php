<?php

namespace App\Services\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockIn;
use App\Repositories\Stock\ReceiptRepositoryInterface;
use App\Repositories\Stock\StockBalanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceiptService
{
    public function __construct(
        protected ReceiptRepositoryInterface $receiptRepository,
        protected StockBalanceRepositoryInterface $stockBalanceRepository,
        protected StockBalanceService $stockBalanceService
    ) {}

    /**
     * Proses penerimaan barang.
     */
    public function store(int $storeId, int $userId, array $data): StockIn
    {
        $this->stockBalanceService->validateStoreOwnership(
            $storeId,
            $data['product_id'],
            $data['warehouse_id']
        );
        // Validasi unit_id sama dengan product.unit_id
        $product = Product::where('id', $data['product_id'])
            ->where('store_id', $storeId)
            ->first();

        if (! $product) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }

        if ($product->unit_id != $data['unit_id']) {
            throw new InvalidArgumentException('Satuan yang dipilih tidak valid untuk produk ini.');
        }

        // Validasi warehouse milik store
        $warehouse = Warehouse::where('id', $data['warehouse_id'])
            ->where('store_id', $storeId)
            ->first();

        if (! $warehouse) {
            throw new InvalidArgumentException('Gudang tidak ditemukan.');
        }

        return DB::transaction(function () use ($storeId, $userId, $data, $product) {
            $code = $this->generateStockInCode();

            // 1. Simpan stock_in
            $receipt = $this->receiptRepository->create([
                'stock_in_code' => $code,
                'store_id' => $storeId,
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'quantity' => $data['quantity'],
                'purchase_price' => $data['purchase_price'] ?? 0,
                'payment_status' => $data['payment_status'],
                'reference_no' => $data['reference_no'] ?? null,
                'received_date' => $data['received_date'],
                'status' => 'selesai',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // 2. Update stok
            $balance = $this->stockBalanceRepository->firstOrCreate(
                $storeId,
                $data['product_id'],
                $data['warehouse_id']
            );

            $balance = $this->stockBalanceRepository->updateQuantity(
                $balance,
                (float) $balance->quantity + (float) $data['quantity'],
                [
                    'last_movement_type' => 'stock_in',
                    'last_reference_no' => $code,
                    'last_quantity_change' => (float) $data['quantity'],
                    'last_changed_by' => $userId,
                    'last_changed_at' => now(),
                ]
            );

            $product->refresh();
            $product->forceFill([
                'initial_stock' => (float) $product->initial_stock + (float) $data['quantity'],
            ]);
            $product->save();

            return $receipt;
        });
    }

    public function getAll(int $storeId, array $filters = [])
    {
        return $this->receiptRepository->getAll($storeId, $filters);
    }

    public function delete(int $storeId, int $receiptId): void
    {
        DB::transaction(function () use ($storeId, $receiptId) {
            $receipt = $this->receiptRepository->findByStoreOrFail($storeId, $receiptId);

            if ($receipt->reference_no && str_starts_with($receipt->reference_no, 'PO-')) {
                throw new InvalidArgumentException('Penerimaan barang dari Purchase Order tidak dapat dihapus secara manual.');
            }

            $balance = $this->stockBalanceRepository->firstOrCreate(
                $storeId,
                $receipt->product_id,
                $receipt->warehouse_id,
            );

            $newQuantity = (float) $balance->quantity - (float) $receipt->quantity;

            if ($newQuantity < 0) {
                throw new InvalidArgumentException('Tidak dapat menghapus: stok akan menjadi negatif.');
            }

            $this->stockBalanceRepository->updateQuantity(
                $balance,
                $newQuantity,
                [
                    'last_movement_type' => 'stock_in_reversal',
                    'last_reference_no' => $receipt->stock_in_code,
                    'last_quantity_change' => -(float) $receipt->quantity,
                    'last_changed_by' => auth()->id(),
                    'last_changed_at' => now(),
                ],
            );

            $product = Product::whereKey($receipt->product_id)->first();

            if ($product) {
                $product->forceFill([
                    'initial_stock' => max(0, (float) $product->initial_stock - (float) $receipt->quantity),
                ]);
                $product->save();
            }

            $this->receiptRepository->delete($receipt);
        });
    }

    protected function generateStockInCode(): string
    {
        $prefix = 'STM';
        $date = now()->format('Ymd');
        $last = StockIn::where('stock_in_code', 'like', "{$prefix}-{$date}-%")
            ->orderBy('stock_in_code', 'desc')
            ->lockForUpdate()
            ->first();

        $sequence = $last ? (int) substr($last->stock_in_code, -3) + 1 : 1;

        return "{$prefix}-{$date}-".str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}
