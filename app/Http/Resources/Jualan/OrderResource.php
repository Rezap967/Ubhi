<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => $this->whenLoaded('customer', function () {
                if (!$this->customer) return null;
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'code' => $this->customer->code ?? null,
                    'phone' => $this->customer->phone ?? null,
                ];
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                if (!$this->warehouse) return null;
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code ?? null,
                ];
            }),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'delivery_method' => $this->delivery_method,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}