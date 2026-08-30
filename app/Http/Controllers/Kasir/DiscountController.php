<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\Discounts\ApplyCouponRequest;
use App\Http\Requests\Kasir\Discounts\RemoveDiscountRequest;
use App\Models\Jualan\Coupon;
use App\Models\Kasir\PosAppliedDiscount;
use App\Repositories\Jualan\CouponRepository;
use App\Services\Jualan\Coupons\CouponService;
use App\Services\Kasir\PosCartService;
use App\Services\Kasir\PricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiscountController extends Controller
{
    public function __construct(
        protected PosCartService $cartService,
        protected CouponRepository $couponRepository,
        protected CouponService $couponService,
        protected PricingService $pricingService,
    ) {}

    /**
     * POST /api/v1/pos/cart/discounts/apply-coupon
     * Apply coupon to active cart (found via device_id).
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        try {
            // Get active cart for user and device
            $cart = $this->cartService->getActiveCart(
                $request->user(),
                $request->input('device_id')
            );

            $couponCode = strtoupper((string) $request->input('coupon_code'));
            $storeId = (int) $request->user()->getStoreId();

            // Find coupon by code for this store
            $coupon = $this->couponRepository->findByCode($couponCode, $storeId);

            if (! $coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode kupon tidak ditemukan.',
                ], 404);
            }

            // Validate eligibility
            $cartSubtotal = (float) ($cart->subtotal_amount ?? $cart->subtotal ?? 0);
            $eligibilityError = $this->couponService->validateEligibility($coupon, $cartSubtotal, null);

            if ($eligibilityError !== '') {
                return response()->json([
                    'success' => false,
                    'message' => $eligibilityError,
                ], 422);
            }

            // Check if already applied
            $alreadyApplied = PosAppliedDiscount::query()
                ->where('cart_id', $cart->id)
                ->where('coupon_id', $coupon->id)
                ->where('status', 'active')
                ->exists();

            if ($alreadyApplied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kupon sudah digunakan pada keranjang ini.',
                ], 422);
            }

            // Calculate discount amount
            $discountAmount = $coupon->calculateDiscount($cartSubtotal);

            // Map coupon discount_type to pos_applied_discounts enum
            $discountTypeMap = [
                'persentase' => 'diskon_persen',
                'nominal' => 'potongan_tetap',
            ];
            $posDiscountType = $discountTypeMap[$coupon->discount_type] ?? 'potongan_tetap';

            // scope must match enum values from migration
            $scope = 'semua_produk';

            // Apply coupon to cart
            $applied = PosAppliedDiscount::create([
                'store_id' => $storeId,
                'cart_id' => $cart->id,
                'coupon_id' => $coupon->id,
                'coupon_name' => $coupon->name ?? $coupon->code,
                'promotion_name' => $coupon->name ?? $coupon->code,
                'scope' => $scope,
                'discount_type' => $posDiscountType,
                'discount_amount' => $discountAmount,
                'source' => 'manual',
                'status' => 'active',
            ]);

            // Recalculate cart totals
            $this->pricingService->recalculateCart($cart);

            // Reload cart with updated totals
            $cart->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Kupon berhasil diterapkan.',
                'data' => [
                    'discount' => [
                        'id' => $applied->id,
                        'cart_id' => $applied->cart_id,
                        'coupon_id' => $applied->coupon_id,
                        'coupon_name' => $applied->coupon_name,
                        'promotion_id' => $applied->promotion_id,
                        'promotion_name' => $applied->promotion_name,
                        'scope' => $applied->scope,
                        'discount_type' => $applied->discount_type,
                        'discount_amount' => (float) $applied->discount_amount,
                        'source' => $applied->source,
                        'status' => $applied->status,
                    ],
                    'cart' => $cart,
                ],
            ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * DELETE /api/v1/pos/cart/discounts/{discount}
     * Remove applied discount (coupon or promotion) from active cart.
     */
    public function removeDiscount(RemoveDiscountRequest $request, PosAppliedDiscount $discount): JsonResponse
    {
        try {
            // Get active cart for user and device
            $cart = $this->cartService->getActiveCart(
                $request->user(),
                $request->input('device_id')
            );

            // Verify discount belongs to this cart and store
            if ($discount->cart_id !== $cart->id) {
                abort(404);
            }

            if ($discount->store_id !== (int) $request->user()->getStoreId()) {
                abort(403);
            }

            // Mark discount as removed
            $discount->update(['status' => 'removed']);

            // Recalculate cart totals (excludes 'removed' discounts)
            $this->pricingService->recalculateCart($cart);

            // Reload cart
            $cart->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Diskon berhasil dihapus.',
                'data' => [
                    'cart' => $cart,
                ],
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * Format HTTP exceptions to JSON response
     */
    private function httpError(HttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    }
}
