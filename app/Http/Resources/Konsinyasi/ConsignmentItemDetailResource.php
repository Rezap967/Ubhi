<?php

namespace App\Http\Resources\Konsinyasi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentItemDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ],
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ],
            'default_warehouse' => $this->defaultWarehouse ? [
                'id' => $this->defaultWarehouse->id,
                'name' => $this->defaultWarehouse->name,
            ] : null,
            'selling_price' => (float) $this->selling_price,
            'consignment_price' => (float) $this->consignment_price,
            'commission_type' => $this->commission_type,
            'commission_value' => (float) $this->commission_value,
            'commission_amount' => $this->calculateCommission(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->createdBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'stock_balances' => $this->stockBalances->map(fn ($balance) => [
                'warehouse_id' => $balance->warehouse_id,
                'warehouse_name' => $balance->warehouse?->name,
                'quantity_available' => (float) $balance->quantity_available,
                'quantity_sold' => (float) $balance->quantity_sold,
                'quantity_returned' => (float) $balance->quantity_returned,
                'last_movement_type' => $balance->last_movement_type,
                'last_changed_at' => $balance->last_changed_at?->toIso8601String(),
            ]),
            'stock_ledger' => $this->stockLedgers->map(fn ($ledger) => [
                'id' => $ledger->id,
                'movement_type' => $ledger->movement_type,
                'quantity_change' => (float) $ledger->quantity_change,
                'quantity_before' => (float) $ledger->quantity_before,
                'quantity_after' => (float) $ledger->quantity_after,
                'reference_no' => $ledger->reference_no,
                'created_by' => $ledger->createdBy?->name,
                'created_at' => $ledger->created_at?->toIso8601String(),
            ]),
        ];
    }
}