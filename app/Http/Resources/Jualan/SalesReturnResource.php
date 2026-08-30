<?php

namespace App\Http\Resources\Jualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customerCode = $this->customer?->code 
            ?? $this->customer?->customer_code 
            ?? null;

        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'order' => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number ?? $this->order?->code ?? '',
                'customer' => $this->customer ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'code' => $customerCode,
                    'phone' => $this->customer->phone ?? null,
                ] : null,
                'total' => (float) ($this->order?->total ?? $this->order?->grand_total ?? 0),
                'status' => $this->order?->status,
                'created_at' => optional($this->order?->created_at)->toISOString(),
            ],
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'code' => $customerCode,
                'phone' => $this->customer->phone ?? null,
            ] : null,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'refund_method' => $this->refund_method,
            'total_refund' => (float) $this->total_refund,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->approvedBy?->name,
            'approved_at' => optional($this->approved_at)->toISOString(),
            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),
        ];
    }
}