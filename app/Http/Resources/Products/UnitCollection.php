<?php

// app/Http/Resources/Product/UnitCollection.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UnitCollection extends ResourceCollection
{
    public $collects = UnitResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar satuan berhasil dimuat.',
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
