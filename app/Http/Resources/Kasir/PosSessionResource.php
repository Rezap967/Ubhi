<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'device_id' => $this->device_id,
            'status' => $this->status,
            'opening_cash' => (float) $this->opening_cash,
            'closing_cash' => $this->closing_cash !== null ? (float) $this->closing_cash : null,
            'expected_cash' => (float) $this->expected_cash,
            'cash_difference' => $this->cash_difference !== null ? (float) $this->cash_difference : null,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cart' => $this->whenLoaded('activeCart', fn () => new PosCartResource($this->activeCart)),
        ];
    }
}
