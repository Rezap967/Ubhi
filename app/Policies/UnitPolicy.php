<?php

// app/Policies/UnitPolicy.php

namespace App\Policies;

use App\Models\Products\ProductUnit;
use App\Models\User;

class UnitPolicy
{
    protected array $manageRoles = ['owner', 'manajer'];

    public function viewAny(User $user): bool
    {
        return true; // semua role yang lolos auth:sanctum boleh melihat daftar
    }

    public function view(User $user, ProductUnit $unit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function update(User $user, ProductUnit $unit): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function delete(User $user, ProductUnit $unit): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }
}
