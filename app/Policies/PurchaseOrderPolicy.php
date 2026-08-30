<?php

namespace App\Policies;

use App\Models\Belanja\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    protected array $allowedRoles = ['owner', 'manajer', 'staf_gudang'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, $this->allowedRoles, true);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $this->checkStore($user, $purchaseOrder);

        return in_array($user->role?->name, ['owner', 'manajer'], true);
    }

    protected function checkStore(User $user, PurchaseOrder $purchaseOrder): void
    {
        $storeId = $user->profile?->store_id;
        if (! $storeId || $purchaseOrder->store_id !== (int) $storeId) {
            abort(404, 'Purchase Order tidak ditemukan.');
        }
    }
}
