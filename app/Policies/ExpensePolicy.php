<?php

namespace App\Policies;

use App\Models\Accounting\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->canAccess($user) && (int) $expense->store_id === (int) $user->getStoreId();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    protected function canAccess(User $user): bool
    {
        return $user->getStoreId() !== null
            && in_array($user->role()->value('name'), ['owner', 'manajer'], true);
    }
}
