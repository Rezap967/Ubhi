<?php

namespace App\Policies;

use App\Models\Belanja\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    protected array $allowedRoles = ['owner', 'manajer', 'staf_gudang'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function delete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function submit(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function confirm(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    public function cancel(User $user, PurchaseReturn $purchaseReturn): bool
    {
        $this->checkStore($user, $purchaseReturn);

        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    protected function checkStore(User $user, PurchaseReturn $purchaseReturn): void
    {
        $storeId = $user->profile?->store_id;
        if (! $storeId || $purchaseReturn->store_id !== (int) $storeId) {
            abort(404, 'Purchase Return tidak ditemukan.');
        }
    }
}
