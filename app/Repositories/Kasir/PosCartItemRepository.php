<?php

namespace App\Repositories\Kasir;

use App\Models\Kasir\PosCartItem;

class PosCartItemRepository
{
    /**
     * Cari item berdasarkan cart dan product.
     */
    public function findByCartAndProduct(int $cartId, int $productId): ?PosCartItem
    {
        return PosCartItem::query()
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Buat item baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PosCartItem
    {
        return PosCartItem::create($data);
    }

    /**
     * Update item.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PosCartItem $item, array $data): PosCartItem
    {
        $item->update($data);

        return $item->fresh();
    }

    /**
     * Hapus item.
     */
    public function delete(PosCartItem $item): bool
    {
        return (bool) $item->delete();
    }
}
