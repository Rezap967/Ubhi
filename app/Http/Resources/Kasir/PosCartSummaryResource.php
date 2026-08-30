<?php

namespace App\Http\Resources\Kasir;

use App\Services\Kasir\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCartSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricingService = app(PricingService::class);

        return [
            'cart_id' => $this->id,
            'session_id' => $this->session_id,
            'items_count' => $this->items->count(),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'formatted' => [
                'subtotal' => $pricingService->formatMoney($this->subtotal),
                'discount_total' => $pricingService->formatMoney($this->discount_total),
                'tax_total' => $pricingService->formatMoney($this->tax_total),
                'grand_total' => $pricingService->formatMoney($this->grand_total),
            ],
        ];
    }
}
