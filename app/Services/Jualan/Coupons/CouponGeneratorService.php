<?php

namespace App\Services\Jualan\Coupons;

use App\Models\Jualan\Coupon;
use App\Repositories\Jualan\CouponRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponGeneratorService
{
    public function __construct(
        protected CouponRepository $repository,
    ) {}

    /**
     * Generate bulk coupons.
     *
     * @param  array<string, mixed>  $validated
     * @return array{count: int, codes: list<string>}
     */
    public function generate(array $validated, int $storeId, int $creatorId): array
    {
        $quantity = (int) $validated['quantity'];

        if ($quantity < 1 || $quantity > 500) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah kupon harus antara 1 hingga 500.',
            ]);
        }

        $codePrefix = (string) $validated['code_prefix'];
        $namePrefix = (string) ($validated['name_prefix'] ?? 'Kupon');

        $coupons = [];
        $generatedCodes = [];

        for ($i = 0; $i < $quantity; $i++) {
            // Generate unique code with random suffix
            $randomSuffix = Str::random(8);
            $code = strtoupper($codePrefix.'-'.$randomSuffix);

            // Ensure code is unique within store
            while ($this->repository->codeExists($code, $storeId)) {
                $randomSuffix = Str::random(8);
                $code = strtoupper($codePrefix.'-'.$randomSuffix);
            }

            $generatedCodes[] = $code;

            $couponName = "{$namePrefix} ".($i + 1);

            $coupons[] = [
                'store_id' => $storeId,
                'code' => $code,
                'name' => $couponName,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'max_discount' => $validated['max_discount'] ?? null,
                'min_purchase' => $validated['min_purchase'] ?? null,
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_quota' => $validated['total_quota'] ?? null,
                'per_customer_limit' => $validated['per_coupon_limit'] ?? 1,
                'status' => 'aktif',
                'created_by' => $creatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert all coupons
        Coupon::insert($coupons);

        return [
            'count' => $quantity,
            'codes' => $generatedCodes,
        ];
    }
}
