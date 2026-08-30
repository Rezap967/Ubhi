<?php

namespace App\Policies\Jualan;

use App\Models\Jualan\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user) && $coupon->store_id === $user->getStoreId();
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->create($user) && $coupon->store_id === $user->getStoreId();
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->update($user, $coupon);
    }
}
