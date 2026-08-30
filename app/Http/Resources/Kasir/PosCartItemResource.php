<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit_name' => $this->unit_name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (float) $this->quantity,
            'discount_value' => (float) $this->discount_value,
            'subtotal' => (float) $this->subtotal,
            'source' => $this->source,
            'stock_available' => (float) ($this->resource->stock_available ?? 0),
            'stock_status' => $this->resource->stock_status ?? 'available',
        ];
    }
}
