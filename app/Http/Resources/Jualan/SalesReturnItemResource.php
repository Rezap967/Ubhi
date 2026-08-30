<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_code' => $this->product_code,
            'qty_sold' => (float) $this->qty_sold,
            'qty_returned' => (float) $this->qty_returned,
            'unit_price' => (float) $this->unit_price,
            'condition' => $this->condition,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}