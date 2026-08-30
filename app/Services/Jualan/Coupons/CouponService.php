<?php

namespace App\Services\Jualan\Coupons;

use App\Models\Jualan\Coupon;
use App\Models\Jualan\CouponCode;
use App\Models\User;
use App\Repositories\Jualan\CouponRepository;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        protected CouponRepository $repository,
    ) {}

    /**
     * Create a new coupon.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $creator): Coupon
    {
        $validated['store_id'] = (int) $creator->getStoreId();
        $validated['created_by'] = $creator->id;
        $validated['status'] = $validated['status'] ?? 'aktif';

        return $this->repository->create($validated);
    }

    /**
     * Update an existing coupon.
     * Prevent changing discount_type/discount_value if coupon already used.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(Coupon $coupon, array $validated, User $updater): Coupon
    {
        // If coupon has been used, restrict changes to discount_type and discount_value
        if ($coupon->used_count > 0) {
            $restrictedFields = ['discount_type', 'discount_value'];

            foreach ($restrictedFields as $field) {
                if (isset($validated[$field]) && $validated[$field] !== $coupon->{$field}) {
                    throw ValidationException::withMessages([
                        $field => "Tidak bisa mengubah {$field} karena kupon sudah digunakan.",
                    ]);
                }
            }
        }

        $validated['updated_by'] = $updater->id;

        return $this->repository->update($coupon, $validated);
    }

    /**
     * Soft delete a coupon.
     */
    public function delete(Coupon $coupon): bool
    {
        return $this->repository->delete($coupon);
    }

    /**
     * Validate coupon eligibility for use.
     * Returns error message if not eligible, empty string if eligible.
     */
    public function validateEligibility(Coupon $coupon, float $cartSubtotal, ?int $customerId = null): string
    {
        // Check status
        if ($coupon->status !== 'aktif') {
            return 'Kupon tidak aktif.';
        }

        // Check date range
        $today = now()->format('Y-m-d');
        $startDate = $coupon->start_date->format('Y-m-d');
        $endDate = $coupon->end_date->format('Y-m-d');

        if ($startDate > $today) {
            return 'Kupon belum berlaku.';
        }
        if ($endDate < $today) {
            return 'Kupon sudah berakhir.';
        }

        // Check quota
        if ($coupon->isQuotaExhausted()) {
            return 'Kuota kupon sudah habis.';
        }

        // Check minimum purchase
        if ($coupon->min_purchase !== null && $cartSubtotal < (float) $coupon->min_purchase) {
            $minPurchase = number_format($coupon->min_purchase, 0, ',', '.');

            return "Pembelian minimum Rp {$minPurchase} belum terpenuhi.";
        }

        // Check per-customer limit if customer is identified
        if ($customerId !== null) {
            $customerUsageCount = CouponCode::query()
                ->where('coupon_id', $coupon->id)
                ->where('used_by', $customerId)
                ->count();

            if ($customerUsageCount >= $coupon->per_customer_limit) {
                return 'Anda sudah mencapai batas penggunaan kupon ini.';
            }
        }

        return '';
    }

    /**
     * Record coupon usage after successful checkout.
     * Atomically insert coupon_codes and increment used_count.
     */
    public function recordUsage(
        Coupon $coupon,
        ?int $customerId = null,
        ?int $orderId = null,
        ?int $posTransactionId = null,
    ): CouponCode {
        // Check if already redeemed (for POS, check pos_transaction_id)
        if ($posTransactionId !== null) {
            $existing = CouponCode::query()
                ->where('coupon_id', $coupon->id)
                ->where('pos_transaction_id', $posTransactionId)
                ->exists();

            if ($existing) {
                throw new \RuntimeException('Kupon sudah digunakan untuk transaksi ini.');
            }
        }

        // Record in coupon_codes
        $couponCode = CouponCode::create([
            'coupon_id' => $coupon->id,
            'used_by' => $customerId,
            'used_at' => now(),
            'pos_transaction_id' => $posTransactionId,
        ]);

        // Increment used_count
        $coupon->increment('used_count');

        return $couponCode;
    }
}
