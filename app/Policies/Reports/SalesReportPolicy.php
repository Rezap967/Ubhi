<?php

namespace App\Policies\Reports;

use App\Models\User;

class SalesReportPolicy
{
    public function viewSalesReport(User $user): bool
    {
        return $user->hasRole(['owner', 'manajer']);
    }
}
