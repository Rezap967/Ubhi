<?php

namespace App\Repositories\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentStockBalance;
use App\Models\Konsinyasi\ConsignmentStockLedger;
use App\Repositories\Konsinyasi\Contracts\ConsignmentStockRepositoryInterface;
use Illuminate\Support\Collection;

class ConsignmentStockRepository implements ConsignmentStockRepositoryInterface
{
    /**
     * Upsert saldo stok awal dengan row lock untuk mencegah race condition.
     * Dipanggil di dalam DB::transaction() oleh Service layer.
     */
    public function upsertInitialBalance(array $data): ConsignmentStockBalance
    {
        $balance = ConsignmentStockBalance::query()
            ->where('store_id', $data['store_id'])
            ->where('consignment_item_id', $data['consignment_item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->lockForUpdate()
            ->first();

        if ($balance) {
            $balance->quantity_available = (float) $balance->quantity_available + (float) $data['quantity'];
            $balance->last_movement_type = 'initial_receive';
            $balance->last_reference_no = $data['reference_no'];
            $balance->last_changed_by = $data['user_id'];
            $balance->last_changed_at = now();
            $balance->save();

            return $balance;
        }

        return ConsignmentStockBalance::query()->create([
            'store_id' => $data['store_id'],
            'consignment_item_id' => $data['consignment_item_id'],
            'product_id' => $data['product_id'],
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'quantity_available' => $data['quantity'],
            'quantity_sold' => 0,
            'quantity_returned' => 0,
            'last_movement_type' => 'initial_receive',
            'last_reference_no' => $data['reference_no'],
            'last_changed_by' => $data['user_id'],
            'last_changed_at' => now(),
        ]);
    }

    public function recordLedger(array $data): ConsignmentStockLedger
    {
        return ConsignmentStockLedger::query()->create($data);
    }

    public function getBalanceForItem(int $storeId, int $consignmentItemId, ?int $warehouseId = null): ?ConsignmentStockBalance
    {
        return ConsignmentStockBalance::query()
            ->where('store_id', $storeId)
            ->where('consignment_item_id', $consignmentItemId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->first();
    }

    public function getLedgerHistory(int $storeId, int $consignmentItemId): Collection
    {
        return ConsignmentStockLedger::query()
            ->where('store_id', $storeId)
            ->where('consignment_item_id', $consignmentItemId)
            ->orderByDesc('created_at')
            ->get();
    }
}