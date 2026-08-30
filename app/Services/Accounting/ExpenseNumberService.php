<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use Illuminate\Support\Carbon;

class ExpenseNumberService
{
    public function generate(int $storeId, Carbon|string|null $date = null): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?? now());
        $prefix = 'EXP-'.$date->format('Ym').'-';
        $lastNumber = Expense::query()
            ->forStore($storeId)
            ->where('expense_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('expense_no')
            ->value('expense_no');

        $sequence = $lastNumber === null ? 1 : ((int) substr($lastNumber, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
