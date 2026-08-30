<?php

namespace App\Repositories\Jualan;

use App\Models\Jualan\Coupon;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponRepository
{
    /**
     * Paginate coupons for a store with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $storeId): LengthAwarePaginator
    {
        $query = Coupon::query()
            ->where('store_id', $storeId)
            ->whereNull('deleted_at');

        if (! empty($filters['search'])) {
            $query->where('code', 'like', '%'.$filters['search'].'%')
                ->orWhere('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find coupon by code for a store.
     */
    public function findByCode(string $code, int $storeId): ?Coupon
    {
        return Coupon::query()
            ->where('store_id', $storeId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Find coupon by ID for a store.
     */
    public function findForStore(int $id, int $storeId): ?Coupon
    {
        return Coupon::query()
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Create a new coupon.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::create($data);
    }

    /**
     * Update an existing coupon.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $coupon->fresh();
    }

    /**
     * Soft delete a coupon.
     */
    public function delete(Coupon $coupon): bool
    {
        return (bool) $coupon->delete();
    }

    /**
     * Check if code already exists (unique per store).
     */
    public function codeExists(string $code, int $storeId, ?int $excludeId = null): bool
    {
        $query = Coupon::query()
            ->where('store_id', $storeId)
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
