<?php

namespace App\Services\Kasir;

use App\Models\Kasir\PosAppliedDiscount;
use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Services\Jualan\Promotions\PromotionEligibilityService;

class PricingService
{
    public function __construct(
        protected PromotionEligibilityService $promotionEligibilityService,
    ) {}

    /**
     * Hitung ulang seluruh cart — item subtotal, diskon, pajak, grand_total.
     * Ini adalah entry point utama yang dipanggil setiap ada perubahan cart.
     */
    public function recalculateCart(PosCart $cart): PosCart
    {
        $cart->refresh();

        // Recalculate setiap item subtotal agar konsisten
        foreach ($cart->items as $item) {
            $correctSubtotal = $this->calculateItemSubtotal($item);

            if ((float) $item->subtotal !== $correctSubtotal) {
                $item->update(['subtotal' => $correctSubtotal]);
            }
        }

        // Reload items setelah update
        $cart->load('items');

        $subtotal = $this->calculateCartSubtotal($cart);
        $discountTotal = $this->calculateDiscountTotal($cart);
        $taxTotal = $this->calculateTaxTotal($cart);
        $grandTotal = $this->calculateGrandTotal($subtotal, $discountTotal, $taxTotal);

        $cart->update([
            'subtotal' => $subtotal,
            'subtotal_amount' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
        ]);

        return $cart->load('items');
    }

    /**
     * Hitung subtotal satu item cart.
     * item_subtotal = unit_price * quantity  (discount_value belum dikurangi di item)
     */
    public function calculateItemSubtotal(PosCartItem $item): float
    {
        $gross = (float) $item->unit_price * (float) $item->quantity;

        // Guard: subtotal tidak boleh negatif
        return max(round($gross, 2), 0.0);
    }

    /**
     * Hitung subtotal cart = SUM(item subtotal).
     */
    public function calculateCartSubtotal(PosCart $cart): float
    {
        return (float) $cart->items->sum(fn (PosCartItem $item) => (float) $item->subtotal);
    }

    /**
     * Hitung total diskon cart.
     *
     * Tahap ini: return 0.
     * TODO: Ambil dari pos_applied_discounts, coupon_codes, promotions, manual discount.
     */
    public function calculateDiscountTotal(PosCart $cart): float
    {
        $this->syncCartDiscounts($cart);

        return (float) $cart->appliedDiscounts()->where('status', 'active')->sum('discount_amount');
    }

    protected function syncCartDiscounts(PosCart $cart): void
    {
        $cart->load('items');

        if ($cart->items->isEmpty()) {
            $cart->appliedDiscounts()->where('status', 'active')->delete();

            return;
        }

        // Pastikan diskon manual coupon tidak ikut dibersihkan saat cart di-recalculate.
        // Hanya auto-generated promotion yang boleh di-sync ulang.
        $cart->appliedDiscounts()
            ->where('status', 'active')
            ->whereNull('coupon_id')
            ->delete();

        $eligible = $this->promotionEligibilityService->getEligiblePromotionsForCart($cart);
        $best = $this->promotionEligibilityService->selectBestPromotion($eligible);

        if (! $best) {
            return;
        }

        $promotion = $best['promotion'];

        PosAppliedDiscount::query()->create([
            'store_id' => $cart->store_id,
            'cart_id' => $cart->id,
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->name,
            'scope' => $promotion->scope,
            'discount_type' => $promotion->type,
            'discount_amount' => round((float) $best['discount_amount'], 2),
            'source' => 'auto',
            'status' => 'active',
        ]);
    }

    /**
     * Hitung total pajak cart.
     *
     * Tahap ini: return 0.
     * TODO: Ambil dari tax configuration store/product (PPN, PPH, dll).
     */
    public function calculateTaxTotal(PosCart $cart): float
    {
        return 0.0;
    }

    /**
     * Hitung grand total = subtotal - discount + tax.
     * Tidak boleh negatif.
     */
    public function calculateGrandTotal(float $subtotal, float $discountTotal, float $taxTotal): float
    {
        return max(round($subtotal - $discountTotal + $taxTotal, 2), 0.0);
    }

    /**
     * Buat array ringkasan cart yang konsisten.
     *
     * @return array<string, mixed>
     */
    public function getCartSummary(PosCart $cart): array
    {
        return [
            'cart_id' => $cart->id,
            'session_id' => $cart->session_id,
            'items_count' => $cart->items->count(),
            'subtotal' => (float) $cart->subtotal,
            'discount_total' => (float) $cart->discount_total,
            'tax_total' => (float) $cart->tax_total,
            'grand_total' => (float) $cart->grand_total,
        ];
    }

    /**
     * Format angka menjadi string Rupiah.
     * Contoh: 6000 → "Rp 6.000"
     */
    public function formatMoney(int|float|string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
