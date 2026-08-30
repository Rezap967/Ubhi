<?php

namespace App\Http\Resources\Kasir;

use App\Services\Kasir\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = app(PricingService::class);

        return [
            'cart_id' => $this->id,
            'session_id' => $this->session_id,
            'status' => $this->status,
            'items_count' => $this->items->count(),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'formatted' => [
                'subtotal' => $pricing->formatMoney($this->subtotal),
                'discount_total' => $pricing->formatMoney($this->discount_total),
                'tax_total' => $pricing->formatMoney($this->tax_total),
                'grand_total' => $pricing->formatMoney($this->grand_total),
            ],
            'items' => PosCartItemResource::collection($this->whenLoaded('items')),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
                'customer_group' => $this->customer->customer_group_id ? [
                    'id' => $this->customer->customer_group_id,
                    'name' => $this->customer->group?->name,
                ] : null,
            ] : null,
        ];
    }
}
