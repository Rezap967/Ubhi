<?php

namespace App\Policies;

use App\Models\Accounting\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    /**
     * Resolve store_id user dari profile, BUKAN kolom users.store_id.
     * Samakan dengan accessor/helper existing di project jika sudah ada
     * (mis. $user->currentStoreId()) — ganti pemanggilan di bawah bila begitu.
     */
    private function storeId(User $user): ?int
    {
        return $user->profile?->store_id ? (int) $user->profile->store_id : null;
    }

    /**
     * Role owner/manajer sudah digate oleh middleware route ('role:owner,manajer'),
     * jadi policy ini fokus ke store-scoping (IDOR) + business-state check,
     * bukan mengulang pengecekan role.
     */
    public function viewAny(User $user): bool
    {
        return $this->storeId($user) !== null;
    }

    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        $storeId = $this->storeId($user);

        return $storeId !== null && $recurringExpense->store_id === $storeId;
    }

    public function create(User $user): bool
    {
        return $this->storeId($user) !== null;
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->view($user, $recurringExpense);
    }

    public function updateStatus(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->view($user, $recurringExpense);
    }

    public function generate(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->view($user, $recurringExpense);
    }
}