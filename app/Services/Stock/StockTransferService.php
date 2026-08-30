<?php

namespace App\Services\Stock;

use App\Models\Stock\StockTransferItem;
use App\Models\Stock\StockTransferSession;
use App\Repositories\Stock\StockTransferRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        protected StockTransferRepositoryInterface $repository
    ) {}

    /**
     * Ambil daftar transfer stok dengan pagination dan filter.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getList(int $storeId, array $filters): array
    {
        $paginated = $this->repository->paginateTransfers($storeId, $filters);

        $items = collect($paginated->items())->map(fn (StockTransferSession $t) => [
            'id' => (string) $t->id,
            'transfer_code' => $t->transfer_code,
            'transfer_date' => $t->transfer_date?->format('Y-m-d') ?? (string) $t->created_at?->format('Y-m-d'),
            'from_warehouse' => $t->fromWarehouse ? [
                'id' => (string) $t->fromWarehouse->id,
                'name' => $t->fromWarehouse->name,
            ] : null,
            'to_warehouse' => $t->toWarehouse ? [
                'id' => (string) $t->toWarehouse->id,
                'name' => $t->toWarehouse->name,
            ] : null,
            'total_items' => (int) $t->total_items,
            'status' => $t->status,
            'notes' => $t->notes,
            'created_by' => $t->creator ? [
                'id' => (string) $t->creator->id,
                'name' => $t->creator->name,
            ] : null,
            'created_at' => $t->created_at?->toIso8601String(),
        ])->values();

        return [
            'items' => $items,
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ];
    }

    /**
     * Buat draft transfer stok baru (belum mengubah stock_balances).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTransfer(int $storeId, int $userId, array $data): array
    {
        return DB::transaction(function () use ($storeId, $userId, $data) {
            $fromWarehouseId = (int) $data['from_warehouse_id'];
            $toWarehouseId = (int) $data['to_warehouse_id'];

            if ($fromWarehouseId === $toWarehouseId) {
                $this->abortValidationError(['to_warehouse_id' => ['Gudang asal dan tujuan tidak boleh sama.']]);
            }

            $transferCode = $this->generateTransferCode($storeId, $data['transfer_date'] ?? now()->toDateString());

            // Insert Header dengan status DRAFT — belum mempengaruhi stok
            $session = $this->repository->createTransferSession([
                'store_id' => $storeId,
                'transfer_code' => $transferCode,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'transfer_date' => $data['transfer_date'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'total_items' => count($data['items']),
                'created_by' => $userId,
            ]);

            // Insert Items
            $itemRows = [];
            foreach ($data['items'] as $item) {
                $itemRows[] = [
                    'transfer_id' => $session->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ];
            }
            $this->repository->bulkCreateTransferItems($itemRows);

            $warehouses = $this->repository->getWarehousesByIds($storeId, [$fromWarehouseId, $toWarehouseId])->keyBy('id');

            return [
                'id' => (string) $session->id,
                'transfer_code' => $session->transfer_code,
                'transfer_date' => (string) $data['transfer_date'],
                'from_warehouse' => $warehouses->get($fromWarehouseId)?->name ?? '-',
                'to_warehouse' => $warehouses->get($toWarehouseId)?->name ?? '-',
                'total_items' => count($data['items']),
                'status' => 'draft',
            ];
        });
    }

    /**
     * Konfirmasi pengiriman transfer (draft → selesai).
     * Memutasikan stock_balances secara atomic.
     *
     * @return array<string, mixed>
     */
    public function sendTransfer(int $storeId, string|int $transferId, int $userId): array
    {
        return DB::transaction(function () use ($storeId, $transferId, $userId) {
            $transfer = StockTransferSession::query()
                ->where('store_id', $storeId)
                ->where('id', $transferId)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                abort(404, 'Data transfer stok tidak ditemukan.');
            }

            if ($transfer->status !== 'draft') {
                $this->abortValidationError([
                    'status' => ["Transfer stok sudah berstatus '{$transfer->status}' dan tidak dapat dikirim ulang."],
                ]);
            }

            $fromWarehouseId = $transfer->from_warehouse_id;
            $toWarehouseId = $transfer->to_warehouse_id;
            $transferCode = $transfer->transfer_code;
            $now = now();

            // Muat semua items
            $items = $transfer->items()->get();

            // Validasi kecukupan stok seluruh item terlebih dahulu
            $insufficientItems = [];
            $sourceBalances = [];

            foreach ($items as $item) {
                $productId = (int) $item->product_id;
                $requestedQty = (float) $item->quantity;

                $sourceBalance = $this->repository->lockStockBalance($storeId, $productId, $fromWarehouseId);
                $availableStock = (float) ($sourceBalance?->quantity ?? 0);

                if ($availableStock < $requestedQty) {
                    $productName = $item->product?->name ?? "Produk #{$productId}";
                    $insufficientItems[] = [
                        'product_id' => (string) $productId,
                        'product_name' => $productName,
                        'available_stock' => $availableStock,
                        'requested_quantity' => $requestedQty,
                    ];
                } else {
                    $sourceBalances[$productId] = [
                        'balance' => $sourceBalance,
                        'available' => $availableStock,
                        'requested' => $requestedQty,
                    ];
                }
            }

            if (! empty($insufficientItems)) {
                $this->abortInsufficientStock($insufficientItems);
            }

            // Mutasi stok: Asal -qty, Tujuan +qty
            foreach ($items as $item) {
                $productId = (int) $item->product_id;
                $qty = (float) $item->quantity;

                // Gudang Asal → kurangi stok
                $sourceInfo = $sourceBalances[$productId];
                $newSourceQty = $sourceInfo['available'] - $qty;

                $this->repository->updateStockBalance($sourceInfo['balance'], $newSourceQty, [
                    'last_movement_type' => 'transfer_out',
                    'last_reference_no' => $transferCode,
                    'last_quantity_change' => -$qty,
                    'last_changed_by' => $userId,
                    'last_changed_at' => $now,
                ]);

                // Gudang Tujuan → tambah stok
                $destBalance = $this->repository->lockStockBalance($storeId, $productId, $toWarehouseId);
                if (! $destBalance) {
                    $destBalance = $this->repository->createStockBalanceIfMissing($storeId, $productId, $toWarehouseId);
                }

                $newDestQty = (float) $destBalance->quantity + $qty;

                $this->repository->updateStockBalance($destBalance, $newDestQty, [
                    'last_movement_type' => 'transfer_in',
                    'last_reference_no' => $transferCode,
                    'last_quantity_change' => $qty,
                    'last_changed_by' => $userId,
                    'last_changed_at' => $now,
                ]);
            }

            // Update status → selesai
            $transfer->status = 'selesai';
            $transfer->save();

            return $this->getDetail($storeId, $transferId);
        });
    }

    /**
     * Batalkan transfer draft.
     * Jika transfer sudah selesai, lakukan reversal stok.
     *
     * @return array<string, mixed>
     */
    public function cancelTransfer(int $storeId, string|int $transferId, int $userId, string $reason): array
    {
        return DB::transaction(function () use ($storeId, $transferId, $userId, $reason) {
            $transfer = StockTransferSession::query()
                ->where('store_id', $storeId)
                ->where('id', $transferId)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                abort(404, 'Data transfer stok tidak ditemukan.');
            }

            if ($transfer->status === 'dibatalkan') {
                $this->abortValidationError([
                    'status' => ['Transfer stok sudah dibatalkan sebelumnya.'],
                ]);
            }

            $fromWarehouseId = $transfer->from_warehouse_id;
            $toWarehouseId = $transfer->to_warehouse_id;
            $transferCode = $transfer->transfer_code;
            $now = now();

            // Jika sudah selesai → reversal stok (kembalikan ke kondisi sebelum transfer)
            if ($transfer->status === 'selesai') {
                $items = $transfer->items()->get();

                foreach ($items as $item) {
                    $productId = (int) $item->product_id;
                    $qty = (float) $item->quantity;

                    // Kembalikan dari Gudang Tujuan ke Gudang Asal
                    $destBalance = $this->repository->lockStockBalance($storeId, $productId, $toWarehouseId);
                    if ($destBalance) {
                        $newDestQty = max(0, (float) $destBalance->quantity - $qty);
                        $this->repository->updateStockBalance($destBalance, $newDestQty, [
                            'last_movement_type' => 'reversal_transfer',
                            'last_reference_no' => $transferCode,
                            'last_quantity_change' => -$qty,
                            'last_changed_by' => $userId,
                            'last_changed_at' => $now,
                        ]);
                    }

                    // Kembalikan stok ke Gudang Asal
                    $sourceBalance = $this->repository->lockStockBalance($storeId, $productId, $fromWarehouseId);
                    if (! $sourceBalance) {
                        $sourceBalance = $this->repository->createStockBalanceIfMissing($storeId, $productId, $fromWarehouseId);
                    }

                    $newSourceQty = (float) $sourceBalance->quantity + $qty;
                    $this->repository->updateStockBalance($sourceBalance, $newSourceQty, [
                        'last_movement_type' => 'reversal_transfer',
                        'last_reference_no' => $transferCode,
                        'last_quantity_change' => $qty,
                        'last_changed_by' => $userId,
                        'last_changed_at' => $now,
                    ]);
                }
            }

            $transfer->status = 'dibatalkan';
            $transfer->cancel_reason = $reason;
            $transfer->save();

            return $this->getDetail($storeId, $transferId);
        });
    }

    /**
     * Ambil detail transfer stok.
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $storeId, string|int $transferId): array
    {
        $transfer = $this->repository->findTransferDetail($storeId, $transferId);

        if (! $transfer) {
            abort(404, 'Data transfer stok tidak ditemukan.');
        }

        $items = $transfer->items->map(fn (StockTransferItem $item) => [
            'id' => (string) $item->id,
            'product_id' => (string) $item->product_id,
            'sku' => $item->product?->sku ?? '-',
            'product_name' => $item->product?->name ?? '-',
            'quantity' => (float) $item->quantity,
            'unit_name' => $item->product?->unit?->name ?? null,
            'notes' => $item->notes,
        ])->values();

        return [
            'transfer' => [
                'id' => (string) $transfer->id,
                'transfer_code' => $transfer->transfer_code,
                'transfer_date' => $transfer->transfer_date?->format('Y-m-d') ?? (string) $transfer->created_at?->format('Y-m-d'),
                'from_warehouse' => $transfer->fromWarehouse ? [
                    'id' => (string) $transfer->fromWarehouse->id,
                    'name' => $transfer->fromWarehouse->name,
                ] : null,
                'to_warehouse' => $transfer->toWarehouse ? [
                    'id' => (string) $transfer->toWarehouse->id,
                    'name' => $transfer->toWarehouse->name,
                ] : null,
                'status' => $transfer->status,
                'total_items' => (int) $transfer->total_items,
                'notes' => $transfer->notes,
                'cancel_reason' => $transfer->cancel_reason,
                'created_by' => $transfer->creator ? [
                    'id' => (string) $transfer->creator->id,
                    'name' => $transfer->creator->name,
                ] : null,
                'created_at' => $transfer->created_at?->toIso8601String(),
            ],
            'items' => $items,
        ];
    }

    /**
     * Generate format transfer_code: TRF-YYYYMMDD-XXX
     */
    public function generateTransferCode(int $storeId, string $date): string
    {
        $dateStr = now()->parse($date)->format('Ymd');
        $count = $this->repository->countTransfersForDate($storeId, now()->toDateString());
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "TRF-{$dateStr}-{$sequence}";
    }

    /**
     * Lempar exception HTTP 409 jika stok gudang asal tidak mencukupi.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function abortInsufficientStock(array $items): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi untuk beberapa produk.',
                'errors' => [
                    'items' => $items,
                ],
            ], 409)
        );
    }

    /**
     * Lempar exception HTTP 422 jika validasi gagal di layer service.
     *
     * @param  array<string, array<string>>  $errors
     */
    protected function abortValidationError(array $errors): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $errors,
            ], 422)
        );
    }
}
