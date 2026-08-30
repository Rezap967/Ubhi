<?php

namespace App\Policies\Stock;

use App\Models\Stock\StockTransferSession;
use App\Models\User;

class StockTransferPolicy
{
    protected array $allowedRoles = ['owner', 'manajer', 'staf_gudang'];

    protected array $managerRoles = ['owner', 'manajer'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function view(User $user, StockTransferSession $transfer): bool
    {
        if (! in_array($user->role?->name, $this->allowedRoles, true)) {
            return false;
        }

        return $transfer->store_id === $this->resolveStoreId($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function delete(User $user, StockTransferSession $transfer): bool
    {
        if (! in_array($user->role?->name, $this->managerRoles, true)) {
            return false;
        }

        return $transfer->store_id === $this->resolveStoreId($user);
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
