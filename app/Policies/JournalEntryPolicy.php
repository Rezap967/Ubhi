<?php

namespace App\Policies;

use App\Models\Accounting\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $this->canAccess($user) && (int) $journalEntry->store_id === (int) $user->getStoreId();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAccess($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $this->canAccess($user) && (int) $journalEntry->store_id === (int) $user->getStoreId();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $this->canAccess($user) && (int) $journalEntry->store_id === (int) $user->getStoreId();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JournalEntry $journalEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, JournalEntry $journalEntry): bool
    {
        return false;
    }

    protected function canAccess(User $user): bool
    {
        $roleName = $user->role()->value('name');

        return $user->getStoreId() !== null
            && in_array($roleName, ['owner', 'manajer'], true);
    }
}
