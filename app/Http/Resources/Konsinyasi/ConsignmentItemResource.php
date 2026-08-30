<?php

namespace App\Http\Resources\Konsinyasi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentItemResource extends JsonResource
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
            'stock_available' => (float) $this->stockBalances->sum('quantity_available'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}