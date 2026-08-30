<?php

namespace App\Policies;

use App\Models\Products\Product;
use App\Models\User;

class ProductPolicy
{
    protected array $manageRoles = ['owner', 'manajer'];

    protected array $readOnlyRoles = ['kasir', 'staf_gudang'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, [...$this->manageRoles, ...$this->readOnlyRoles], true);
    }

    public function view(User $user, Product $product): bool
    {
        if ($user->role->name === 'owner') {
            return $product->store_id === $user->store?->id;
        }

        if ($user->role->name === 'kasir') {
            return $product->status === 'aktif';
        }

        return in_array($user->role->name, $this->readOnlyRoles, true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function update(User $user, Product $product): bool
    {
        $storeId = $user->getStoreId();

        if ($product->store_id !== $storeId) {
            abort(403, 'Produk bukan milik toko Anda.');
        }

        return in_array($user->role?->name, $this->manageRoles, true);
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->role->name === 'owner') {
            return $product->store_id === $user->store?->id;
        }

        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function updateStatus(User $user, Product $product): bool
    {
        if ($user->role->name === 'owner') {
            return $product->store_id === $user->store?->id;
        }

        return in_array($user->role->name, $this->manageRoles, true);
    }
}
