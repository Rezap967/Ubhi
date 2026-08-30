<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_code' => $this->product_code,
            'quantity' => (float) $this->quantity,
        ];
    }
}