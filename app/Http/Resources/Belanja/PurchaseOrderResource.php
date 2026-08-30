<?php

namespace App\Http\Resources\Belanja;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier_id' => $this->supplier_id,
            'store_id' => $this->store_id,
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'total' => (float) $this->total,
            'total_formatted' => $this->total_formatted,
            'status' => $this->status,
            'jenis_pembayaran' => $this->jenis_pembayaran,
            'estimasi_tiba' => $this->estimasi_tiba ? $this->estimasi_tiba->format('Y-m-d') : null,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => $this->relationLoaded('items') ? $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'purchase_order_id' => $item->purchase_order_id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'qty_ordered' => (int) $item->qty_ordered,
                    'qty_received' => (int) $item->qty_received,
                    'qty_pending' => (int) $item->qty_pending,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'subtotal_formatted' => $item->subtotal_formatted,
                    'expiry_date' => $item->expiry_date ? $item->expiry_date->format('Y-m-d') : null,
                ];
            }) : null,
        ];
    }
}
