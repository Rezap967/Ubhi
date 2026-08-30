<?php

namespace App\Services\Kasir;

use App\Models\Kasir\PosReceipt;
use App\Models\Kasir\PosTransaction;

class ReceiptService
{
    /**
     * Get or create a receipt snapshot for a given transaction.
     * If an existing receipt has an outdated payload format, it will be migrated automatically.
     */
    public function getReceipt(PosTransaction $transaction): PosReceipt
    {
        $receipt = $transaction->receipt;

        if (! $receipt) {
            $transaction->loadMissing(['items.product', 'payments', 'store', 'user']);

            $receipt = PosReceipt::create([
                'transaction_id' => $transaction->id,
                'receipt_number' => 'RCT-'.$transaction->transaction_number,
                'receipt_payload' => $this->buildPayload($transaction),
                'print_count' => 0,
                'printed_at' => null,
            ]);
        } elseif (! isset($receipt->receipt_payload['transaction'])) {
            // Migrate legacy flat payload format to the nested structure.
            $transaction->loadMissing(['items.product', 'payments', 'store', 'user']);

            $receipt->receipt_payload = $this->buildPayload($transaction);
            $receipt->save();
        }

        return $receipt;
    }

    /**
     * Increment the print count and update the printed_at timestamp.
     */
    public function printReceipt(PosTransaction $transaction): PosReceipt
    {
        $receipt = $this->getReceipt($transaction);

        $receipt->increment('print_count');
        $receipt->printed_at = now();
        $receipt->save();

        return $receipt;
    }

    /**
     * Build the canonical receipt payload from a fully-loaded transaction.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(PosTransaction $transaction): array
    {
        return [
            'transaction' => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'transaction_at' => $transaction->transaction_at->toIso8601String(),
                'subtotal' => $transaction->subtotal,
                'discount_total' => $transaction->discount_total,
                'tax_total' => $transaction->tax_total,
                'grand_total' => $transaction->grand_total,
                'paid_amount' => $transaction->paid_amount,
                'change_amount' => $transaction->change_amount,
                'status' => $transaction->status,
                'notes' => $transaction->notes,
            ],
            'store' => [
                'id' => $transaction->store->id,
                'name' => $transaction->store->name,
                'address' => $transaction->store->address,
                'phone' => $transaction->store->phone,
            ],
            'cashier' => [
                'id' => $transaction->user->id,
                'name' => $transaction->user->name,
            ],
            'items' => $transaction->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'sku' => $item->product ? $item->product->sku : null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'total_price' => $item->total_price,
                ];
            })->toArray(),
            'payments' => $transaction->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'payment_at' => $payment->payment_at ? $payment->payment_at->toIso8601String() : null,
                ];
            })->toArray(),
        ];
    }
}
