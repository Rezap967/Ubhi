<?php

// app/Http/Resources/Products/Warehouses/WarehouseCollection.php

namespace App\Http\Resources\Products\Warehouses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WarehouseCollection extends ResourceCollection
{
    public $collects = WarehouseResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar gudang berhasil dimuat.',
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }
}
