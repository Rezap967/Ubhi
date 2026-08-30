<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'change_amount' => (float) $this->change_amount,
            'receipt_number' => $this->receipt?->receipt_number,
            'transaction_at' => $this->transaction_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'discount_amount' => (float) $item->discount_amount,
                'subtotal' => (float) $item->subtotal,
            ])
            ),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'payment_method' => $p->payment_method,
                'amount' => (float) $p->amount,
                'change_amount' => (float) $p->change_amount,
                'reference_number' => $p->reference_number,
                'status' => $p->status,
            ])
            ),
        ];
    }
}
