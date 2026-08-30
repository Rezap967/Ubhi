<?php

namespace App\Policies\Reports;

use App\Models\Kasir\PosTransaction;
use App\Models\User;

class TransactionHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['owner', 'manajer', 'kasir']);
    }

    public function view(User $user, PosTransaction $transaction): bool
    {
        if ($user->hasRole(['owner', 'manajer'])) {
            return true;
        }

        if ($user->hasRole('kasir')) {
            return $transaction->user_id === $user->id;
        }

        return false;
    }
}
