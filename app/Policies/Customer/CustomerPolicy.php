<?php

namespace App\Policies\Customer;

use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'kasir'], true);
    }

    public function view(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'kasir'], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer'], true);
    }

    public function delete(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer'], true);
    }

    public function quickCreateFromPOS(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'kasir'], true);
    }
}
