<?php

namespace App\Policies\Customer;

use App\Models\Customer\CustomerGroup;
use App\Models\User;

class CustomerGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'kasir']);
    }

    public function view(User $user, CustomerGroup $group): bool
    {
        return $this->viewAny($user) && $group->store_id === $user->store_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer']);
    }

    public function update(User $user, CustomerGroup $group): bool
    {
        return $this->create($user) && $group->store_id === $user->store_id;
    }

    public function delete(User $user, CustomerGroup $group): bool
    {
        return $this->update($user, $group);
    }
}
