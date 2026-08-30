<?php

namespace App\Services\Belanja\PurchaseOrders;

use App\Exceptions\Belanja\PurchaseOrderException;
use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseOrderItem;
use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockIn;
use App\Services\Stock\StockMovementService;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseOrderService
{
    public function __construct(
        protected ?StockMovementService $stockMovementService = null
    ) {}

    /**
     * Membuat Purchase Order baru dalam transaksi DB.
     */
    public function create(int $storeId, int $userId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($storeId, $userId, $data) {
            $poNumber = $this->generateUniquePoNumber($storeId);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'store_id' => $storeId,
                'status' => 'Draft',
                'notes' => $data['notes'] ?? null,
                'jenis_pembayaran' => $data['jenis_pembayaran'],
                'estimasi_tiba' => $data['estimasi_tiba'] ?? null,
                'created_by' => $userId,
                'total' => 0, // Dihitung oleh observer
            ]);

            foreach ($data['items'] as $itemData) {
                $subtotal = $itemData['qty_ordered'] * $itemData['unit_price'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $itemData['product_id'],
                    'qty_ordered' => $itemData['qty_ordered'],
                    'qty_received' => 0,
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $subtotal,
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                ]);
            }

            return $po->load(['supplier', 'items.product']);
        });
    }

    /**
     * Mengupdate Purchase Order (Hanya berstatus Draft).
     */
    public function update(PurchaseOrder $po, int $userId, array $data): PurchaseOrder
    {
        if ($po->status !== 'Draft') {
            throw new PurchaseOrderException('Hanya Purchase Order berstatus Draft yang dapat diedit.', 403);
        }

        return DB::transaction(function () use ($po, $userId, $data) {
            $po->update([
                'supplier_id' => $data['supplier_id'],
                'notes' => $data['notes'] ?? null,
                'jenis_pembayaran' => $data['jenis_pembayaran'],
                'estimasi_tiba' => $data['estimasi_tiba'] ?? null,
                'updated_by' => $userId,
            ]);

            // Sync items: Hapus item lama, masukkan item baru
            // Observer akan mendeteksi deleted dan saved untuk recalculate total
            $po->items()->delete();

            foreach ($data['items'] as $itemData) {
                $subtotal = $itemData['qty_ordered'] * $itemData['unit_price'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $itemData['product_id'],
                    'qty_ordered' => $itemData['qty_ordered'],
                    'qty_received' => 0,
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $subtotal,
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                ]);
            }

            return $po->load(['supplier', 'items.product']);
        });
    }

    /**
     * Soft delete Purchase Order.
     */
    public function delete(PurchaseOrder $po, int $userId): void
    {
        if ($po->status !== 'Draft') {
            throw new PurchaseOrderException('Hanya Purchase Order berstatus Draft yang dapat dihapus.', 403);
        }

        DB::transaction(function () use ($po, $userId) {
            /**
             * KEPUTUSAN FINAL:
             * Tabel purchase_order_items tidak memiliki kolom deleted_at (tidak memakai SoftDeletes),
             * sehingga items tidak perlu/tidak bisa di-soft-delete terpisah secara eksplisit di kolom deleted_at.
             * Items tetap dibiarkan menempel ke header yang di-soft-delete.
             */
            $po->update([
                'updated_by' => $userId,
            ]);

            $po->delete();
        });
    }

    /**
     * Mengirim Purchase Order (Draft -> Dikirim).
     */
    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'Draft') {
            throw new PurchaseOrderException('Hanya Purchase Order berstatus Draft yang dapat dikirim ke supplier.', 422);
        }

        $po->update([
            'status' => 'Dikirim',
        ]);

        return $po;
    }

    /**
     * Membatalkan Purchase Order (Draft/Dikirim -> Dibatalkan).
     */
    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        if (! in_array($po->status, ['Draft', 'Dikirim'], true)) {
            throw new PurchaseOrderException('Hanya Purchase Order berstatus Draft atau Dikirim yang dapat dibatalkan.', 422);
        }

        $po->update([
            'status' => 'Dibatalkan',
        ]);

        return $po;
    }

    /**
     * Menerima barang/item Purchase Order secara bertahap atau lunas dengan integrasi stok berjalan.
     */
    public function receiveItems(PurchaseOrder $po, array $data, ?int $userId = null): PurchaseOrder
    {
        if (! in_array($po->status, ['Dikirim', 'Sebagian'], true)) {
            throw new PurchaseOrderException('Hanya Purchase Order berstatus Dikirim atau Sebagian yang dapat diproses.', 422);
        }

        return DB::transaction(function () use ($po, $data, $userId) {
            $stockMovementService = $this->stockMovementService ?? app(StockMovementService::class);

            // Tentukan gudang tujuan: dari input request atau fallback ke default / gudang pertama store
            $warehouseId = $data['warehouse_id'] ?? null;
            if ($warehouseId === null) {
                $defaultWarehouse = Warehouse::where('store_id', $po->store_id)->first();
                $warehouseId = $defaultWarehouse?->id;
            }

            foreach ($data['items'] as $itemInput) {
                $item = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('id', $itemInput['purchase_order_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw new PurchaseOrderException('Item Purchase Order tidak ditemukan atau bukan milik PO ini.', 422);
                }

                $qtyReceivedNow = (int) $itemInput['qty_received'];
                $pendingQty = (int) $item->qty_ordered - (int) $item->qty_received;

                if ($qtyReceivedNow > $pendingQty) {
                    throw new PurchaseOrderException(
                        "Jumlah diterima untuk produk {$item->product->name} ({$qtyReceivedNow}) tidak boleh melebihi sisa pesanan ({$pendingQty}).",
                        422
                    );
                }

                $newQtyReceived = (int) $item->qty_received + $qtyReceivedNow;

                $item->update([
                    'qty_received' => $newQtyReceived,
                ]);

                // Tambah initial_stock produk (sumber kebenaran stok di seluruh aplikasi)
                // Konsisten dengan ReceiptService & CheckoutService yang membaca/menulis ke kolom ini.
                $product = Product::where('id', $item->product_id)
                    ->where('store_id', $po->store_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->forceFill([
                        'initial_stock' => (float) $product->initial_stock + $qtyReceivedNow,
                    ])->save();
                }

                // Sinkronisasi stock_balances dan pencatatan stock_movements
                $stockMovementService->recordReceipt(
                    storeId: (int) $po->store_id,
                    productId: (int) $item->product_id,
                    warehouseId: $warehouseId !== null ? (int) $warehouseId : null,
                    quantity: (float) $qtyReceivedNow,
                    poId: (int) $po->id,
                    poItemId: (int) $item->id,
                    userId: $userId,
                    notes: "Penerimaan PO #{$po->po_number} (Item #{$item->id})"
                );

                // Tambahkan pencatatan stock_ins agar riwayat terima barang muncul di menu Terima Barang
                $stockInCode = $this->generateStockInCode();
                StockIn::create([
                    'stock_in_code' => $stockInCode,
                    'store_id' => $po->store_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'supplier_id' => $po->supplier_id,
                    'quantity' => $qtyReceivedNow,
                    'purchase_price' => $item->unit_price,
                    'payment_status' => $po->jenis_pembayaran === 'Tunai' ? 'lunas' : 'belum_lunas',
                    'reference_no' => $po->po_number,
                    'received_date' => now()->format('Y-m-d'),
                    'status' => 'selesai',
                    'notes' => "Penerimaan PO #{$po->po_number} (Item #{$item->id})",
                    'created_by' => $userId,
                ]);
            }

            // Hitung status PO berdasarkan item
            $po->refresh();
            $allReceived = true;
            foreach ($po->items as $item) {
                if ($item->qty_pending > 0) {
                    $allReceived = false;
                    break;
                }
            }

            $newStatus = $allReceived ? 'Diterima' : 'Sebagian';

            $po->update([
                'status' => $newStatus,
                'updated_by' => $userId ?? $po->updated_by,
            ]);

            return $po->load(['supplier', 'items.product']);
        });
    }

    /**
     * Alias untuk receiveItems (backward compatibility).
     */
    public function receive(PurchaseOrder $po, array $data, ?int $userId = null): PurchaseOrder
    {
        return $this->receiveItems($po, $data, $userId);
    }

    /**
     * Generate PO number dengan retry max 3x.
     */
    protected function generateUniquePoNumber(int $storeId): string
    {
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                return DB::transaction(function () use ($storeId) {
                    $date = now()->format('Ymd');

                    // lockForUpdate hitung seq hari ini PER STORE
                    $count = PurchaseOrder::where('store_id', $storeId)
                        ->whereDate('created_at', today())
                        ->lockForUpdate()
                        ->count();

                    $seq = $count + 1;
                    $hex = strtoupper(str_pad(dechex($seq), 4, '0', STR_PAD_LEFT));
                    $poNumber = "PO-{$date}-{$hex}";

                    // Pastikan tidak ada collision
                    if (! PurchaseOrder::where('po_number', $poNumber)->exists()) {
                        return $poNumber;
                    }

                    throw new \Exception("Collision detected for PO number: {$poNumber}");
                });
            } catch (Throwable $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw new PurchaseOrderException('Gagal menghasilkan nomor Purchase Order unik setelah 3 kali percobaan.', 500);
                }
            }
        }

        throw new PurchaseOrderException('Terjadi kesalahan pada server.', 500);
    }

    /**
     * Generate code STM untuk data stock_ins.
     */
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
