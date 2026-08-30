<?php

namespace App\Http\Resources\Products\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierCollection extends ResourceCollection
{
    public $collects = SupplierResource::class;

    /**
     * @return array<int, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(fn ($supplier) => (new SupplierResource($supplier))->toArray($request))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar pemasok berhasil dimuat.',
            'meta' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ],
        ];
    }
}
