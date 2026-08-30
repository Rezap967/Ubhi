<?php

namespace App\Services\Jualan\Promotions;

use App\Models\Jualan\Promotion;
use App\Models\Kasir\PosCart;
use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Collection;

class PromotionEligibilityService
{
    public function getEligiblePromotionsForCart(PosCart $cart): Collection
    {
        $promotions = Promotion::query()
            ->where('store_id', $cart->store_id)
            ->where('status', 'aktif')
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereNull('deleted_at')
            ->with('targets')
            ->get();

        $eligible = new Collection;

        foreach ($promotions as $promotion) {
            $eligibleSubtotal = $this->calculateEligibleSubtotal($cart, $promotion);
            $eligibleQty = $this->calculateEligibleQty($cart, $promotion);

            if ($eligibleSubtotal <= 0 || $eligibleQty <= 0) {
                continue;
            }

            if ($promotion->min_purchase !== null && $eligibleSubtotal < (float) $promotion->min_purchase) {
                continue;
            }

            if ($promotion->min_qty !== null && $eligibleQty < (int) $promotion->min_qty) {
                continue;
            }

            if ($promotion->max_usage !== null && (int) $promotion->usage_count >= (int) $promotion->max_usage) {
                continue;
            }

            $discountAmount = $this->calculatePromotionDiscount($promotion, $eligibleSubtotal);

            $eligible->push([
                'promotion' => $promotion,
                'eligible_subtotal' => $eligibleSubtotal,
                'eligible_qty' => $eligibleQty,
                'discount_amount' => $discountAmount,
            ]);
        }

        return $eligible;
    }

    public function calculatePromotionDiscount(Promotion $promotion, float $eligibleSubtotal): float
    {
        $discount = 0.0;
        $amount = max((float) $eligibleSubtotal, 0.0);

        if ($promotion->type === 'diskon_persen') {
            $discount = ($amount * (float) $promotion->discount_value) / 100;
            if ($promotion->max_discount !== null) {
                $discount = min($discount, (float) $promotion->max_discount);
            }
        }

        if ($promotion->type === 'potongan_tetap') {
            $discount = (float) $promotion->discount_value;
        }

        $discount = min(max($discount, 0.0), $amount);

        return round($discount, 2);
    }

    public function selectBestPromotion(Collection $eligiblePromotions): ?array
    {
        if ($eligiblePromotions->isEmpty()) {
            return null;
        }

        return $eligiblePromotions->sortBy([
            ['priority', 'desc'],
            ['discount_amount', 'desc'],
            ['promotion.id', 'asc'],
        ])->first();
    }

    public function calculateEligibleSubtotal(PosCart $cart, Promotion $promotion): float
    {
        $items = $cart->items()->with('product')->get();

        if ($items->isEmpty()) {
            if ((float) $cart->subtotal > 0) {
                return $this->isPromotionScopeEligibleForCart($promotion) ? round((float) $cart->subtotal, 2) : 0.0;
            }

            return 0.0;
        }

        $eligibleSubtotal = 0.0;

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if ($this->isItemEligibleForPromotion($item, $product, $promotion)) {
                $eligibleSubtotal += (float) $item->subtotal;
            }
        }

        return round($eligibleSubtotal, 2);
    }

    public function calculateEligibleQty(PosCart $cart, Promotion $promotion): float
    {
        $items = $cart->items()->with('product')->get();

        if ($items->isEmpty()) {
            return $this->isPromotionScopeEligibleForCart($promotion) && (float) $cart->subtotal > 0 ? 1.0 : 0.0;
        }

        $qty = 0.0;

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if ($this->isItemEligibleForPromotion($item, $product, $promotion)) {
                $qty += (float) $item->quantity;
            }
        }

        return $qty;
    }

    protected function isPromotionScopeEligibleForCart(Promotion $promotion): bool
    {
        return $promotion->scope === 'semua_produk' || $promotion->scope === 'per_grup_pelanggan';
    }

    protected function isItemEligibleForPromotion($item, Product $product, Promotion $promotion): bool
    {
        if ($promotion->scope === 'semua_produk') {
            return true;
        }

        if ($promotion->scope === 'per_produk') {
            return $promotion->targets()->where('target_type', 'produk')->where('target_id', $product->id)->exists();
        }

        if ($promotion->scope === 'per_kategori') {
            return $promotion->targets()->where('target_type', 'kategori')->where('target_id', $product->category_id)->exists();
        }

        if ($promotion->scope === 'per_brand') {
            return $promotion->targets()->where('target_type', 'brand')->where('target_id', $product->brand_id)->exists();
        }

        if ($promotion->scope === 'per_grup_pelanggan') {
            return true;
        }

        return false;
    }
}
