<?php

// app/Policies/WarehousePolicy.php

namespace App\Policies;

use App\Models\Products\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'staf_gudang'], true);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'staf_gudang'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'staf_gudang'], true);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return in_array($user->role->name, ['owner', 'manajer'], true);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return in_array($user->role->name, ['owner', 'manajer'], true);
    }
}
