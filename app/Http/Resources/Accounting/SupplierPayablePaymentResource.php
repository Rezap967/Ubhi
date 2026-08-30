<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPayablePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_date_label' => $this->payment_date?->translatedFormat('d M Y'),
            'amount' => (float) $this->amount,
            'amount_label' => 'Rp '.number_format((float) $this->amount, 0, ',', '.'),
            'payment_method' => $this->payment_method,
            'reference_no' => $this->reference_no,
            'notes' => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
        ];
    }
}
