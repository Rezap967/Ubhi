<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosProductResource extends JsonResource
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
            'plu' => $this->plu ?? null,
            'barcode' => $this->barcode,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null,
            'unit' => $this->unit ? $this->unit->name : null,
            'price' => (float) ($this->price ?? $this->sell_price),
            'stock_available' => (float) $this->stock_available,
            'stock_status' => $this->stock_status,
            'can_be_sold' => (bool) $this->can_be_sold,
        ];
    }
}
