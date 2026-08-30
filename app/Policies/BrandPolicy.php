<?php

// app/Policies/BrandPolicy.php

namespace App\Policies;

use App\Models\Products\Brand;
use App\Models\User;

class BrandPolicy
{
    protected array $manageRoles = ['owner', 'manajer'];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Brand $brand): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function update(User $user, Brand $brand): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }
}
