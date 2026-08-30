<?php

// app/Http/Resources/Products/ProductCollection.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public $collects = ProductResource::class;

    /** Disable default pagination wrapping so we control the structure fully. */
    public function preserveAllQueryParameters(): bool
    {
        return false;
    }

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar produk berhasil dimuat.',
            'data' => $this->collection,
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

    /** Remove the 'links' and default 'meta' key that ResourceCollection injects. */
    public function with(Request $request): array
    {
        return [];
    }

    public function withResponse($request, $response): void
    {
        $data = $response->getData(true);
        // Remove auto-injected 'links' key from ResourceCollection
        unset($data['links']);
        $response->setData($data);
    }
}
