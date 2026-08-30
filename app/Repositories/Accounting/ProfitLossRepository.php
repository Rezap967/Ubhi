<?php

namespace App\Repositories\Accounting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossRepository
{
    /** @return Collection<int, object> */
    public function getRevenueAccounts(int $storeId, array $filters): Collection
    {
        return $this->getReportAccounts($storeId, 'revenue', $filters);
    }

    /** @return Collection<int, object> */
    public function getExpenseAccounts(int $storeId, array $filters): Collection
    {
        return $this->getReportAccounts($storeId, 'expense', $filters);
    }

    /** @return Collection<int, object> */
    public function getReportAccounts(int $storeId, string $type, array $filters): Collection
    {
        $totalDebit = 'COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jel.debit ELSE 0 END), 0)';
        $totalCredit = 'COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jel.credit ELSE 0 END), 0)';
        $amount = $type === 'revenue'
            ? "{$totalCredit} - {$totalDebit}"
            : "{$totalDebit} - {$totalCredit}";

        return DB::table('accounts as a')
            ->leftJoin('journal_entry_lines as jel', function ($join) use ($storeId): void {
                $join->on('jel.account_id', '=', 'a.id')
                    ->where('jel.store_id', $storeId);
            })
            ->leftJoin('journal_entries as je', function ($join) use ($storeId, $filters): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', $storeId)
                    ->where('je.status', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereBetween('je.journal_date', [$filters['date_start'], $filters['date_end']]);
            })
            ->where('a.store_id', $storeId)
            ->whereNull('a.deleted_at')
            ->where('a.type', $type)
            ->select([
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.type',
                'a.normal_balance',
                'a.is_active',
            ])
            ->selectRaw("{$totalDebit} as total_debit")
            ->selectRaw("{$totalCredit} as total_credit")
            ->selectRaw("{$amount} as amount")
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance', 'a.is_active')
            ->orderBy('a.code')
            ->get();
    }
}
