<?php

// app/Policies/CategoryPolicy.php

namespace App\Policies;

use App\Models\Products\Category;
use App\Models\User;

class CategoryPolicy
{
    protected array $manageRoles = ['owner'];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function update(User $user, Category $category): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }

    public function delete(User $user, Category $category): bool
    {
        return in_array($user->role->name, $this->manageRoles, true);
    }
}
