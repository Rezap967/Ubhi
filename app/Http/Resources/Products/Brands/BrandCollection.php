<?php

// app/Http/Resources/Product/BrandCollection.php

namespace App\Http\Resources\Products\Brands;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BrandCollection extends ResourceCollection
{
    public $collects = BrandResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar merek berhasil dimuat.',
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
