<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'code' => $this->product->code ?? null,
                    'sku' => $this->product->sku ?? null,
                    'price' => (float) ($this->product->price ?? 0),
                    'sell_price' => (float) ($this->product->sell_price ?? $this->product->price ?? 0),
                    'stock' => (float) ($this->product->stock ?? 0),
                ];
            }),
            'product_name' => $this->product_name,
            'product_code' => $this->product_code,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (float) $this->quantity,
            'discount_value' => (float) $this->discount_value,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}