<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_number' => $this->delivery_number,
            'source_type' => $this->source_type,
            'order' => $this->source_type === 'transaksi_penjualan' && $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number ?? $this->order->code ?? '',
                'created_at' => optional($this->order->created_at)->toISOString(),
                'customer_name' => $this->order->customer?->name,
                'recipient_name' => $this->recipient_name,
                'recipient_address' => $this->recipient_address,
            ] : null,
            'customer_order' => $this->source_type === 'order_pelanggan' && $this->customerOrder ? [
                'id' => $this->customerOrder->id,
                'order_number' => $this->customerOrder->order_number ?? $this->customerOrder->code ?? '',
                'customer_order_number' => $this->customerOrder->order_number ?? $this->customerOrder->code ?? '',
                'created_at' => optional($this->customerOrder->created_at)->toISOString(),
                'customer_name' => $this->customerOrder->customer?->name,
                'recipient_name' => $this->recipient_name,
                'recipient_address' => $this->recipient_address,
            ] : null,
            'recipient_name' => $this->recipient_name,
            'recipient_address' => $this->recipient_address,
            'delivery_date' => optional($this->delivery_date)->format('Y-m-d'),
            'status' => $this->status,
            'printed_at' => optional($this->printed_at)->toISOString(),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'items' => DeliveryNoteItemResource::collection($this->whenLoaded('items')),
        ];
    }
}