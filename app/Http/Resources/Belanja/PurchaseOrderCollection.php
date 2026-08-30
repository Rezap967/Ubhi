<?php

namespace App\Http\Resources\Belanja;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseOrderCollection extends ResourceCollection
{
    public $collects = PurchaseOrderResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar Purchase Order berhasil dimuat.',
            'data' => $this->collection,
        ];
    }
}
