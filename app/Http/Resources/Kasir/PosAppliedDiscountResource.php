<?php

namespace App\Http\Resources\Kasir;

use App\Models\Kasir\PosAppliedDiscount;
use Illuminate\Http\Resources\Json\JsonResource;

class PosAppliedDiscountResource extends JsonResource
{
    public function __construct(PosAppliedDiscount $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'promotion_id' => $this->promotion_id,
            'promotion_name' => $this->promotion_name,
            'scope' => $this->scope,
            'discount_type' => $this->discount_type,
            'discount_amount' => (float) $this->discount_amount,
            'source' => $this->source,
            'status' => $this->status,
        ];
    }
}
