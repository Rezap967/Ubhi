<?php

namespace App\Policies\Jualan;

use App\Models\Jualan\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->viewAny($user) && $promotion->store_id === $user->getStoreId();
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->create($user) && $promotion->store_id === $user->getStoreId();
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->update($user, $promotion);
    }
}
