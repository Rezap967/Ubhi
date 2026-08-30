<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get all unique payment methods for this transaction
        $paymentMethods = $this->payments->pluck('payment_method')->unique()->values()->all();

        return [
            'id' => $this->id,
            'receipt_no' => $this->transaction_number,
            'transaction_at' => $this->transaction_at->toDateTimeString(),
            'cashier' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'customer' => $this->customer_id ? [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
            ] : null,
            'status' => $this->status,
            'payment_method' => $paymentMethods[0] ?? null,
            'payment_methods' => $paymentMethods,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
        ];
    }
}
