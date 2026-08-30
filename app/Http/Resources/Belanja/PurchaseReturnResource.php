<?php

namespace App\Http\Resources\Belanja;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'store_id' => $this->store_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'total' => (float) $this->total,
            'total_formatted' => $this->total_formatted,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Loaded relations
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,

            'purchase_order' => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status,
            ] : null,

            'items' => $this->relationLoaded('items') ? $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'purchase_return_id' => $item->purchase_return_id,
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'product_id' => $item->product_id,
                    'qty_returned' => (int) $item->qty_returned,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'subtotal_formatted' => $item->subtotal_formatted,
                    'reason' => $item->reason,
                    'status_item' => $item->status_item,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'purchase_order_item' => $item->purchaseOrderItem ? [
                        'id' => $item->purchaseOrderItem->id,
                        'qty_ordered' => (int) $item->purchaseOrderItem->qty_ordered,
                        'qty_received' => (int) $item->purchaseOrderItem->qty_received,
                    ] : null,
                ];
            }) : null,
        ];
    }
}
