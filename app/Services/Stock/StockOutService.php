<?php

namespace App\Services\Stock;

use App\Models\Products\Product;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockOut;
use App\Repositories\Stock\StockBalanceRepositoryInterface;
use App\Repositories\Stock\StockOutRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockOutService
{
    public function __construct(
        protected StockOutRepositoryInterface $stockOutRepository,
        protected StockBalanceRepositoryInterface $stockBalanceRepository,
        protected StockBalanceService $stockBalanceService
    ) {}

    /**
     * Proses pengeluaran barang (non-penjualan).
     */
    public function store(int $storeId, int $userId, array $data): StockOut
    {
        [$product, $warehouse] = $this->stockBalanceService->validateStoreOwnership(
            $storeId,
            $data['product_id'],
            $data['warehouse_id']
        );

        // Validasi unit_id sama dengan product.unit_id
        if ($product->unit_id != $data['unit_id']) {
            throw new InvalidArgumentException('Satuan yang dipilih tidak valid untuk produk ini.');
        }

        // Generate kode pengeluaran
        $code = $this->generateStockOutCode();

        return DB::transaction(function () use ($storeId, $userId, $data, $code, $product) {
            // 1. Cek stok mencukupi (menggunakan lockForUpdate)
            $balance = $this->stockBalanceRepository->findByProductAndWarehouse(
                $storeId,
                $data['product_id'],
                $data['warehouse_id']
            );

            if (! $balance) {
                $balance = $this->stockBalanceRepository->firstOrCreate(
                    $storeId,
                    $data['product_id'],
                    $data['warehouse_id']
                );

                if ((float) $balance->quantity <= 0 && (float) $product->initial_stock > 0) {
                    $balance = $this->stockBalanceRepository->updateQuantity(
                        $balance,
                        (float) $product->initial_stock,
                        [
                            'last_movement_type' => 'stock_sync',
                            'last_quantity_change' => (float) $product->initial_stock,
                            'last_changed_by' => $userId,
                            'last_changed_at' => now(),
                        ]
                    );
                }
            }

            // Lock row untuk mencegah race condition
            $balance = StockBalance::where('id', $balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentQuantity = (float) $balance->quantity;

            if ($currentQuantity < $data['quantity']) {
                throw new InvalidArgumentException(
                    "Stok tidak mencukupi. Tersedia: {$currentQuantity}, diminta: {$data['quantity']}."
                );
            }

            // 2. Simpan stock_out
            $stockOut = $this->stockOutRepository->create([
                'stock_out_code' => $code,
                'store_id' => $storeId,
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'reference_no' => $data['reference_no'] ?? null,
                'out_date' => $data['out_date'],
                'status' => 'selesai',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // 3. Kurangi stok
            $newQuantity = $currentQuantity - $data['quantity'];
            $this->stockBalanceRepository->updateQuantity(
                $balance,
                $newQuantity,
                [
                    'last_movement_type' => 'stock_out',
                    'last_reference_no' => $code,
                    'last_quantity_change' => -$data['quantity'],
                    'last_changed_by' => $userId,
                    'last_changed_at' => now(),
                ]
            );

            // 4. Kurangi stok pada product.initial_stock agar tampilan produk ikut berkurang.
            $product->forceFill([
                'initial_stock' => max(0, (float) $product->initial_stock - (float) $data['quantity']),
            ]);
            $product->save();

            return $stockOut;
        });
    }

    public function list(int $storeId): array
    {
        return StockOut::with(['product:id,name', 'warehouse:id,name'])
            ->where('store_id', $storeId)
            ->orderBy('out_date', 'desc')
            ->get()
            ->map(fn (StockOut $stockOut) => [
                'id' => $stockOut->id,
                'out_date' => $stockOut->out_date->toDateString(),
                'product' => [
                    'name' => $stockOut->product?->name,
                ],
                'quantity' => $stockOut->quantity,
                'reason' => $stockOut->reason,
                'warehouse' => [
                    'name' => $stockOut->warehouse?->name,
                ],
                'reference_no' => $stockOut->reference_no,
            ])
            ->all();
    }

    protected function generateStockOutCode(): string
    {
        $prefix = 'KLR';
        $date = now()->format('Ymd');
        $last = StockOut::where('stock_out_code', 'like', "{$prefix}-{$date}-%")
            ->orderBy('stock_out_code', 'desc')
            ->first();

        $sequence = $last ? (int) substr($last->stock_out_code, -3) + 1 : 1;

        return "{$prefix}-{$date}-".str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}
