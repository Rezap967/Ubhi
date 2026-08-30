<?php

namespace App\Services\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentItem;
use App\Repositories\Konsinyasi\Contracts\ConsignmentItemRepositoryInterface;
use App\Repositories\Konsinyasi\Contracts\ConsignmentStockRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsignmentItemService
{
    public function __construct(
        protected ConsignmentItemRepositoryInterface $itemRepository,
        protected ConsignmentStockRepositoryInterface $stockRepository,
    ) {
    }

    public function getPaginatedItems(array $filters, int $storeId): LengthAwarePaginator
    {
        return $this->itemRepository->paginateForStore($storeId, $filters);
    }

    public function getSummaryStatistics(int $storeId): array
    {
        return $this->itemRepository->getSummaryStatistics($storeId);
    }

    public function getDetail(int $storeId, int $id): ConsignmentItem
    {
        return $this->itemRepository->findForStoreOrFail($storeId, $id);
    }

    public function getBySupplier(int $storeId, int $supplierId): \Illuminate\Support\Collection
    {
        return $this->itemRepository->getBySupplierForStore($storeId, $supplierId);
    }

    public function createConsignmentItem(array $data, int $storeId, int $userId): ConsignmentItem
    {
        if ($this->itemRepository->existsDuplicate($storeId, (int) $data['product_id'], (int) $data['supplier_id'])) {
            throw ValidationException::withMessages([
                'product_id' => 'Kombinasi produk dan supplier ini sudah terdaftar sebagai barang konsinyasi.',
            ]);
        }

        return DB::transaction(function () use ($data, $storeId, $userId) {
            $item = $this->itemRepository->create([
                'store_id' => $storeId,
                'product_id' => $data['product_id'],
                'supplier_id' => $data['supplier_id'],
                'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
                'selling_price' => $data['selling_price'],
                'consignment_price' => $data['consignment_price'],
                'commission_type' => $data['commission_type'],
                'commission_value' => $data['commission_value'],
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $initialQty = (float) ($data['initial_quantity'] ?? 0);

            if ($initialQty > 0) {
                $warehouseId = $data['warehouse_id'] ?? $data['default_warehouse_id'] ?? null;
                $referenceNo = $data['reference_no']
                    ?? ('KONS-INIT-' . date('Ymd') . '-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT));

                $this->stockRepository->upsertInitialBalance([
                    'store_id' => $storeId,
                    'consignment_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'supplier_id' => $item->supplier_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $initialQty,
                    'reference_no' => $referenceNo,
                    'user_id' => $userId,
                ]);

                $this->stockRepository->recordLedger([
                    'store_id' => $storeId,
                    'consignment_item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => 'initial_receive',
                    'quantity_change' => $initialQty,
                    'quantity_before' => 0.00,
                    'quantity_after' => $initialQty,
                    'reference_type' => 'consignment_item',
                    'reference_id' => $item->id,
                    'reference_no' => $referenceNo,
                    'created_by' => $userId,
                ]);
            }

            return $item->load(['product', 'supplier', 'stockBalances', 'defaultWarehouse']);
        });
    }

    public function updateConsignmentItem(ConsignmentItem $item, array $data, int $storeId): ConsignmentItem
    {
        if ($item->store_id !== $storeId) {
            abort(404);
        }

        return DB::transaction(function () use ($item, $data) {
            $payload = array_filter([
                'selling_price' => $data['selling_price'] ?? null,
                'consignment_price' => $data['consignment_price'] ?? null,
                'commission_type' => $data['commission_type'] ?? null,
                'commission_value' => $data['commission_value'] ?? null,
                'status' => $data['status'] ?? null,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            ], fn ($value) => $value !== null);

            if (array_key_exists('notes', $data)) {
                $payload['notes'] = $data['notes'];
            }

            return $this->itemRepository->update($item, $payload);
        });
    }

    public function getStockRealtime(int $storeId, int $consignmentItemId, ?int $warehouseId = null): ?array
    {
        $balance = $this->stockRepository->getBalanceForItem($storeId, $consignmentItemId, $warehouseId);

        if (!$balance) {
            return null;
        }

        return [
            'quantity_available' => (float) $balance->quantity_available,
            'quantity_sold' => (float) $balance->quantity_sold,
            'quantity_returned' => (float) $balance->quantity_returned,
            'last_movement_type' => $balance->last_movement_type,
            'last_changed_at' => $balance->last_changed_at,
        ];
    }
}