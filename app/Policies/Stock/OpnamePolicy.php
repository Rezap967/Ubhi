<?php

namespace App\Policies\Stock;

use App\Models\Stock\StockOpnameSession;
use App\Models\User;

class OpnamePolicy
{
    protected array $allowedRoles = ['owner', 'manajer', 'staf_gudang'];

    protected array $approveRoles = ['owner', 'manajer'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function view(User $user, StockOpnameSession $session): bool
    {
        if (! in_array($user->role?->name, $this->allowedRoles, true)) {
            return false;
        }

        return $session->store_id === $this->resolveStoreId($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function updateItem(User $user, StockOpnameSession $session): bool
    {
        if (! in_array($user->role?->name, $this->allowedRoles, true)) {
            return false;
        }

        return $session->store_id === $this->resolveStoreId($user);
    }

    public function approve(User $user, StockOpnameSession $session): bool
    {
        if (! in_array($user->role?->name, $this->approveRoles, true)) {
            return false;
        }

        return $session->store_id === $this->resolveStoreId($user);
    }

    /**
     * Resolve store_id user secara aman — return null jika belum punya store.
     */
    private function resolveStoreId(User $user): ?int
    {
        try {
            return $user->activeStoreId();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
