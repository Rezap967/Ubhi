<?php

namespace App\Repositories\Accounting;

use Illuminate\Support\Facades\DB;

class BalanceSheetRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getReportAccounts(int $storeId, string $type, array $filters): array
    {
        $asOfDate = $filters['as_of_date'] ?? now()->toDateString();

        return DB::table('accounts as a')
            ->leftJoin('journal_entry_lines as jel', function ($join) use ($storeId): void {
                $join->on('jel.account_id', '=', 'a.id')
                    ->where('jel.store_id', '=', $storeId);
            })
            ->leftJoin('journal_entries as je', function ($join) use ($storeId, $asOfDate): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', '=', $storeId)
                    ->where('je.status', '=', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereDate('je.journal_date', '<=', $asOfDate);
            })
            ->where('a.store_id', $storeId)
            ->where('a.type', $type)
            ->whereNull('a.deleted_at')
            ->select(
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.type',
                'a.normal_balance',
                'a.is_active',
                DB::raw('COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jel.debit ELSE 0 END), 0) as total_debit'),
                DB::raw('COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jel.credit ELSE 0 END), 0) as total_credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance', 'a.is_active')
            ->orderBy('a.code', 'asc')
            ->get()
            ->map(function ($row) {
                $totalDebit = (float) $row->total_debit;
                $totalCredit = (float) $row->total_credit;

                // Calculate amount based on account type
                if ($row->type === 'asset') {
                    // Asset: normal balance is debit
                    $amount = $totalDebit - $totalCredit;
                } else {
                    // Liability and Equity: normal balance is credit
                    $amount = $totalCredit - $totalDebit;
                }

                return (array) $row + ['amount' => round($amount, 2)];
            })
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAssetAccounts(int $storeId, array $filters): array
    {
        return $this->getReportAccounts($storeId, 'asset', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getLiabilityAccounts(int $storeId, array $filters): array
    {
        return $this->getReportAccounts($storeId, 'liability', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getEquityAccounts(int $storeId, array $filters): array
    {
        return $this->getReportAccounts($storeId, 'equity', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getRunningProfit(int $storeId, array $filters): float
    {
        $asOfDate = $filters['as_of_date'] ?? now()->toDateString();

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) use ($storeId, $asOfDate): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', '=', $storeId)
                    ->where('je.status', '=', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereDate('je.journal_date', '<=', $asOfDate);
            })
            ->join('accounts as a', function ($join) use ($storeId): void {
                $join->on('a.id', '=', 'jel.account_id')
                    ->where('a.store_id', '=', $storeId)
                    ->whereNull('a.deleted_at')
                    ->whereIn('a.type', ['revenue', 'expense']);
            })
            ->where('jel.store_id', $storeId)
            ->selectRaw('
                SUM(CASE WHEN a.type = "revenue" THEN jel.credit - jel.debit ELSE 0 END) as total_revenue,
                SUM(CASE WHEN a.type = "expense" THEN jel.debit - jel.credit ELSE 0 END) as total_expense
            ')
            ->first();

        $totalRevenue = round((float) ($result->total_revenue ?? 0), 2);
        $totalExpense = round((float) ($result->total_expense ?? 0), 2);

        return round($totalRevenue - $totalExpense, 2);
    }
}
