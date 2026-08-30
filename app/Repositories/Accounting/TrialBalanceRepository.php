<?php

namespace App\Repositories\Accounting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TrialBalanceRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function paginateMutations(int $storeId, string $accountId, array $filters): LengthAwarePaginator
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) use ($storeId, $filters): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', $storeId)
                    ->where('je.status', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereBetween('je.journal_date', [$filters['date_start'], $filters['date_end']]);
            })
            ->join('accounts as a', function ($join) use ($storeId, $accountId): void {
                $join->on('a.id', '=', 'jel.account_id')
                    ->where('a.id', $accountId)
                    ->where('a.store_id', $storeId)
                    ->whereNull('a.deleted_at');
            })
            ->where('jel.store_id', $storeId)
            ->select([
                'jel.id',
                'jel.journal_entry_id',
                'je.journal_no',
                'je.journal_date',
                'je.description',
                'je.source_type',
                'je.source_id',
                'jel.debit',
                'jel.credit',
            ])
            ->orderByDesc('je.journal_date')
            ->orderByDesc('je.journal_no')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_debit: float, total_credit: float, ending_balance: float}
     */
    public function mutationSummary(int $storeId, string $accountId, array $filters): array
    {
        $totals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) use ($storeId, $filters): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', $storeId)
                    ->where('je.status', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereBetween('je.journal_date', [$filters['date_start'], $filters['date_end']]);
            })
            ->where('jel.store_id', $storeId)
            ->where('jel.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(jel.credit), 0) as total_credit')
            ->first();

        $debit = round((float) ($totals->total_debit ?? 0), 2);
        $credit = round((float) ($totals->total_credit ?? 0), 2);

        return [
            'total_debit' => $debit,
            'total_credit' => $credit,
            'ending_balance' => round($debit - $credit, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(int $storeId, array $filters): LengthAwarePaginator
    {
        $mutationDebit = 'COALESCE(SUM(CASE WHEN je.id IS NULL THEN 0 ELSE jel.debit END), 0)';
        $mutationCredit = 'COALESCE(SUM(CASE WHEN je.id IS NULL THEN 0 ELSE jel.credit END), 0)';
        $endingBalance = "CASE WHEN a.normal_balance = 'debit' THEN {$mutationDebit} - {$mutationCredit} ELSE {$mutationCredit} - {$mutationDebit} END";
        $sortColumns = [
            'code' => 'a.code',
            'name' => 'a.name',
            'type' => 'a.type',
            'normal_balance' => 'a.normal_balance',
            'mutation_debit' => 'mutation_debit',
            'mutation_credit' => 'mutation_credit',
            'total_debit' => 'total_debit',
            'total_credit' => 'total_credit',
            'ending_balance' => 'ending_balance',
        ];

        $query = DB::table('accounts as a')
            ->leftJoin('journal_entry_lines as jel', function ($join) use ($storeId): void {
                $join->on('jel.account_id', '=', 'a.id')
                    ->where('jel.store_id', '=', $storeId);
            })
            ->leftJoin('journal_entries as je', function ($join) use ($storeId, $filters): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', '=', $storeId)
                    ->where('je.status', '=', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereBetween('je.journal_date', [$filters['date_start'], $filters['date_end']]);
            })
            ->where('a.store_id', $storeId)
            ->whereNull('a.deleted_at')
            ->select([
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.type',
                'a.normal_balance',
                'a.is_active',
            ])
            ->selectRaw("{$mutationDebit} as mutation_debit")
            ->selectRaw("{$mutationCredit} as mutation_credit")
            ->selectRaw("{$mutationDebit} as total_debit")
            ->selectRaw("{$mutationCredit} as total_credit")
            ->selectRaw("{$endingBalance} as ending_balance")
            ->groupBy(
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.normal_balance',
                'a.is_active',
            );

        $this->applyAccountFilters($query, $filters, $mutationDebit, $mutationCredit, $endingBalance);

        $sortBy = (string) ($filters['sort_by'] ?? 'code');
        $sortDirection = (string) ($filters['sort_direction'] ?? 'asc');

        return $query
            ->orderBy($sortColumns[$sortBy] ?? 'a.code', $sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_mutation_debit: float, total_mutation_credit: float, total_debit: float, total_credit: float, difference: float, is_balanced: bool}
     */
    public function summary(int $storeId, array $filters): array
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) use ($storeId, $filters): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.store_id', '=', $storeId)
                    ->where('je.status', '=', 'posted')
                    ->whereNull('je.deleted_at')
                    ->whereBetween('je.journal_date', [$filters['date_start'], $filters['date_end']]);
            })
            ->join('accounts as a', function ($join) use ($storeId): void {
                $join->on('a.id', '=', 'jel.account_id')
                    ->where('a.store_id', '=', $storeId)
                    ->whereNull('a.deleted_at');
            })
            ->where('jel.store_id', $storeId);

        $this->applyAccountWhereFilters($query, $filters);

        $totals = $query
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_mutation_debit')
            ->selectRaw('COALESCE(SUM(jel.credit), 0) as total_mutation_credit')
            ->first();

        $debit = round((float) ($totals->total_mutation_debit ?? 0), 2);
        $credit = round((float) ($totals->total_mutation_credit ?? 0), 2);

        return [
            'total_mutation_debit' => $debit,
            'total_mutation_credit' => $credit,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'difference' => round(abs($debit - $credit), 2),
            'is_balanced' => round($debit, 2) === round($credit, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyAccountFilters(Builder $query, array $filters, string $mutationDebit, string $mutationCredit, string $endingBalance): void
    {
        $this->applyAccountWhereFilters($query, $filters);

        $query->when(($filters['show_zero_balance'] ?? false) === false, function (Builder $query) use ($mutationDebit, $mutationCredit, $endingBalance): void {
            $query->havingRaw("{$mutationDebit} <> 0 OR {$mutationCredit} <> 0 OR {$endingBalance} <> 0");
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyAccountWhereFilters(Builder $query, array $filters): void
    {
        $query->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
            $search = '%'.mb_strtolower((string) $filters['search']).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(a.code) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(a.name) LIKE ?', [$search]);
            });
        });

        $query->when(($filters['account_id'] ?? null) !== null, fn (Builder $query) => $query->where('a.id', $filters['account_id']))
            ->when(($filters['type'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('a.type', $filters['type']))
            ->when(($filters['status'] ?? 'all') === 'active', fn (Builder $query) => $query->where('a.is_active', true))
            ->when(($filters['status'] ?? 'all') === 'inactive', fn (Builder $query) => $query->where('a.is_active', false));
    }
}
