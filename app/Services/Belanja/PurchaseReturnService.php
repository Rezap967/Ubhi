<?php

namespace App\Services\Belanja;

use App\Exceptions\Belanja\PurchaseReturnException;
use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseOrderItem;
use App\Models\Belanja\PurchaseReturn;
use App\Models\Belanja\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseReturnService
{
    /**
     * Membuat Purchase Return baru.
     */
    public function create(int $storeId, int $userId, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($storeId, $userId, $data) {
            $po = PurchaseOrder::where('id', $data['purchase_order_id'])
                ->where('store_id', $storeId)
                ->first();

            if (! $po) {
                throw new PurchaseReturnException('Purchase Order asal tidak ditemukan.', 404);
            }

            if (! in_array($po->status, ['Dikirim', 'Sebagian', 'Diterima'], true)) {
                throw new PurchaseReturnException('Purchase Order harus berstatus Dikirim, Sebagian, atau Diterima sebelum dapat diretur.', 422);
            }

            $returnNumber = $this->generateUniqueReturnNumber($storeId);

            $pr = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'store_id' => $storeId,
                'status' => 'Draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'total' => 0, // Dihitung oleh observer
            ]);

            foreach ($data['items'] as $index => $itemData) {
                $poItem = PurchaseOrderItem::where('id', $itemData['purchase_order_item_id'])
                    ->where('purchase_order_id', $po->id)
                    ->first();

                if (! $poItem) {
                    throw new PurchaseReturnException('Item retur tidak sesuai dengan Purchase Order yang dipilih.', 422);
                }

                if ($itemData['qty_returned'] > $poItem->qty_ordered) {
                    $errors = [
                        "items.{$index}.qty_returned" => [
                            "Qty retur ({$itemData['qty_returned']}) melebihi qty pesan pada PO asal ({$poItem->qty_ordered}).",
                        ],
                    ];
                    throw new PurchaseReturnException(
                        'Jumlah retur tidak boleh melebihi jumlah barang pada PO asal.',
                        422,
                        $errors
                    );
                }

                $subtotal = $itemData['qty_returned'] * $poItem->unit_price;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $pr->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'qty_returned' => $itemData['qty_returned'],
                    'unit_price' => $poItem->unit_price,
                    'subtotal' => $subtotal,
                    'reason' => $itemData['reason'] ?? null,
                    'status_item' => 'Pending',
                ]);
            }

            return $pr->load(['supplier', 'items.product', 'purchaseOrder']);
        });
    }

    /**
     * Memperbarui Purchase Return (Draft).
     */
    public function update(PurchaseReturn $pr, int $userId, array $data): PurchaseReturn
    {
        if ($pr->status !== 'Draft') {
            throw new PurchaseReturnException('Hanya Retur berstatus Draft yang dapat diedit.', 403);
        }

        if ((int) $data['purchase_order_id'] !== (int) $pr->purchase_order_id) {
            throw new PurchaseReturnException('PO asal tidak boleh diubah pada retur.', 422);
        }

        return DB::transaction(function () use ($pr, $userId, $data) {
            $pr->update([
                'notes' => $data['notes'] ?? null,
                'updated_by' => $userId,
            ]);

            // Sync items: Hapus item lama, masukkan item baru
            $pr->items()->delete();

            foreach ($data['items'] as $index => $itemData) {
                $poItem = PurchaseOrderItem::where('id', $itemData['purchase_order_item_id'])
                    ->where('purchase_order_id', $pr->purchase_order_id)
                    ->first();

                if (! $poItem) {
                    throw new PurchaseReturnException('Item retur tidak sesuai dengan Purchase Order yang dipilih.', 422);
                }

                if ($itemData['qty_returned'] > $poItem->qty_ordered) {
                    $errors = [
                        "items.{$index}.qty_returned" => [
                            "Qty retur ({$itemData['qty_returned']}) melebihi qty pesan pada PO asal ({$poItem->qty_ordered}).",
                        ],
                    ];
                    throw new PurchaseReturnException(
                        'Jumlah retur tidak boleh melebihi jumlah barang pada PO asal.',
                        422,
                        $errors
                    );
                }

                $subtotal = $itemData['qty_returned'] * $poItem->unit_price;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $pr->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'qty_returned' => $itemData['qty_returned'],
                    'unit_price' => $poItem->unit_price,
                    'subtotal' => $subtotal,
                    'reason' => $itemData['reason'] ?? null,
                    'status_item' => 'Pending',
                ]);
            }

            return $pr->load(['supplier', 'items.product', 'purchaseOrder']);
        });
    }

    /**
     * Soft delete Purchase Return.
     */
    public function delete(PurchaseReturn $pr, int $userId): void
    {
        if ($pr->status !== 'Draft') {
            throw new PurchaseReturnException('Hanya Retur berstatus Draft yang dapat dihapus.', 403);
        }

        DB::transaction(function () use ($pr, $userId) {
            $pr->update([
                'updated_by' => $userId,
            ]);

            $pr->delete();
        });
    }

    /**
     * Submit Purchase Return (Draft -> Dikirim ke Supplier).
     */
    public function submit(PurchaseReturn $pr): PurchaseReturn
    {
        if ($pr->status !== 'Draft') {
            throw new PurchaseReturnException('Hanya Retur berstatus Draft yang dapat dikirim ke supplier.', 422);
        }

        $pr->update([
            'status' => 'Dikirim ke Supplier',
        ]);

        return $pr;
    }

    /**
     * Konfirmasi Purchase Return (Dikirim ke Supplier -> Dikonfirmasi).
     */
    public function confirm(PurchaseReturn $pr, int $userId): PurchaseReturn
    {
        if ($pr->status !== 'Dikirim ke Supplier') {
            throw new PurchaseReturnException('Hanya Retur berstatus Dikirim ke Supplier yang dapat dikonfirmasi.', 422);
        }

        return DB::transaction(function () use ($pr, $userId) {
            $pr->update([
                'status' => 'Dikonfirmasi',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            $pr->items()->update([
                'status_item' => 'Dikonfirmasi',
            ]);

            return $pr->load(['supplier', 'items.product', 'purchaseOrder']);
        });
    }

    /**
     * Batalkan Purchase Return.
     */
    public function cancel(PurchaseReturn $pr): PurchaseReturn
    {
        if ($pr->status === 'Dikonfirmasi') {
            throw new PurchaseReturnException('Retur yang sudah Dikonfirmasi tidak dapat dibatalkan.', 422);
        }

        if (! in_array($pr->status, ['Draft', 'Dikirim ke Supplier'], true)) {
            throw new PurchaseReturnException('Retur tidak dapat dibatalkan.', 422);
        }

        $pr->update([
            'status' => 'Dibatalkan',
        ]);

        return $pr;
    }

    /**
     * Generate Return Number dengan retry max 3x.
     */
    protected function generateUniqueReturnNumber(int $storeId): string
    {
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                return DB::transaction(function () use ($storeId) {
                    $date = now()->format('Ymd');

                    $count = PurchaseReturn::where('store_id', $storeId)
                        ->whereDate('created_at', today())
                        ->lockForUpdate()
                        ->count();

                    $seq = $count + 1;
                    $number = 'RT-'.$date.'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);

                    if (! PurchaseReturn::where('return_number', $number)->exists()) {
                        return $number;
                    }

                    throw new \Exception("Collision detected for return number: {$number}");
                });
            } catch (Throwable $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw new PurchaseReturnException('Gagal menghasilkan nomor Retur unik setelah 3 kali percobaan.', 500);
                }
            }
        }

        throw new PurchaseReturnException('Terjadi kesalahan pada server.', 500);
    }
}
