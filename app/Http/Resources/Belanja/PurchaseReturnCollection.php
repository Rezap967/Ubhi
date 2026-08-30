<?php

namespace App\Http\Resources\Belanja;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseReturnCollection extends ResourceCollection
{
    public $collects = PurchaseReturnResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar Purchase Return berhasil dimuat.',
            'data' => $this->collection,
        ];
    }
}
