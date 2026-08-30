<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'receipt_number' => $this->receipt_number,
            'receipt_payload' => $this->receipt_payload,
            'print_count' => $this->print_count,
            'printed_at' => $this->printed_at ? $this->printed_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
