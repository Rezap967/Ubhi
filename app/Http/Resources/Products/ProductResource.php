<?php

// app/Http/Resources/Products/ProductResource.php

namespace App\Http\Resources\Products;

use App\Models\Products\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCashier = $request->user()?->role === 'kasir';

        /** @var Product $this */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Harga jual selalu tampil
            'sell_price' => $this->sell_price,

            // Harga beli & margin disembunyikan dari kasir (RBAC SAD §1)
            'buy_price' => $isCashier ? null : $this->buy_price,
            'margin' => $isCashier ? null : $this->margin,

            'initial_stock' => (int) ($this->initial_stock ?? 0),

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),

            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
            ]),

            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
            ]),

            'variant_count' => $this->relationLoaded('children') ? $this->children->count() : 0,

            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'is_primary' => $img->is_primary,
                'sort_order' => $img->sort_order,
            ])),

            'special_prices' => $this->whenLoaded('specialPrices', fn () => $this->specialPrices->map(fn ($sp) => [
                'id' => $sp->id,
                'min_qty' => $sp->min_qty,
                'max_qty' => $sp->max_qty,
                'special_price' => $sp->special_price,
                'is_active' => $sp->is_active,
                'valid_from' => $sp->valid_from?->toDateString(),
                'valid_until' => $sp->valid_until?->toDateString(),
            ])),

            'suppliers' => $this->whenLoaded('suppliers', fn () => $this->suppliers->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])),

            /**
             * Varian anak (children) hanya disertakan jika relasi sudah di-load.
             * Kasir tetap mendapat children tapi tanpa buy_price/margin di tiap anak.
             */
            'children' => $this->whenLoaded('children', fn () => ProductResource::collection($this->children)->resolve($request)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
