<?php

namespace App\Policies\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentItem;
use App\Models\User;

class ConsignmentItemPolicy
{
    /**
     * Role yang diizinkan mengelola konsinyasi: Owner, Petugas Gudang.
     * Kasir TIDAK diizinkan (403).
     */
    protected function hasAccess(User $user): bool
    {
        if ($user->store_id === null) {
            return false;
        }

        $roleName = strtolower($user->role?->name ?? '');

        return in_array($roleName, ['owner', 'petugas_gudang', 'manajer'], true);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, ConsignmentItem $item): bool
    {
        if (!$this->hasAccess($user)) {
            return false;
        }

        return $item->store_id === $user->store_id;
    }

    public function create(User $user): bool
    {
        $roleName = strtolower($user->role?->name ?? '');

        return $user->store_id !== null && $roleName === 'owner';
    }

    public function update(User $user, ConsignmentItem $item): bool
    {
        $roleName = strtolower($user->role?->name ?? '');

        if ($roleName !== 'owner') {
            return false;
        }

        return $item->store_id === $user->store_id;
    }

    public function delete(User $user, ConsignmentItem $item): bool
    {
        $roleName = strtolower($user->role?->name ?? '');

        if ($roleName !== 'owner') {
            return false;
        }

        return $item->store_id === $user->store_id;
    }
}