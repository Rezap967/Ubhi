<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopSellingProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'sold_quantity' => (int) $this->sold_quantity,
            'sales_total' => (float) $this->sales_total,
            'price' => (float) ($this->price ?? $this->sell_price),
            'stock_available' => (float) $this->stock_available,
            'stock_status' => $this->stock_status,
        ];
    }
}
