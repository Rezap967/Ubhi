<?php

namespace App\Repositories\Kasir;

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosSession;

class PosCartRepository
{
    /**
     * Buat cart aktif baru untuk session yang baru dibuka.
     */
    public function createActiveCartForSession(PosSession $session): PosCart
    {
        return PosCart::create([
            'store_id' => $session->store_id,
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'customer_id' => null,
            'status' => 'active',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
        ]);
    }

    /**
     * Ambil cart aktif untuk session tertentu.
     */
    public function findActiveCartBySession(int $sessionId): ?PosCart
    {
        return PosCart::query()
            ->where('session_id', $sessionId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Lock cart for pessimistic update.
     */
    public function lockCart(int $cartId): PosCart
    {
        return PosCart::query()
            ->lockForUpdate()
            ->findOrFail($cartId);
    }

    /**
     * Load cart dengan items.
     */
    public function loadCart(PosCart $cart): PosCart
    {
        return $cart->load(['items', 'customer.group']);
    }

    /**
     * Hapus semua item dari cart.
     */
    public function deleteItems(PosCart $cart): void
    {
        $cart->items()->delete();
    }
}
