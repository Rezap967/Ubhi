<?php

namespace App\Services\Accounting;

use App\Http\Resources\Accounting\TrialBalanceRowResource;
use App\Models\Accounting\Account;
use App\Repositories\Accounting\TrialBalanceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TrialBalanceService
{
    public function __construct(protected TrialBalanceRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator<int, object>, summary: array<string, mixed>, filters: array<string, mixed>, period: array{date_start: string, date_end: string}, grouped: array<int, array<string, mixed>>}
     */
    public function getReport(int $storeId, array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        Log::debug('Trial Balance Filters', $normalizedFilters);
        Log::debug('Trial Balance Store ID', ['store_id' => $storeId]);
        $rows = $this->repository->paginate($storeId, $normalizedFilters);

        return [
            'rows' => $rows,
            'summary' => $this->repository->summary($storeId, $normalizedFilters),
            'filters' => $normalizedFilters,
            'period' => [
                'date_start' => $normalizedFilters['date_start'],
                'date_end' => $normalizedFilters['date_end'],
            ],
            'grouped' => $this->groupRowsIfNeeded(
                array_map(static fn (object $row): array => (array) $row, $rows->items()),
                $normalizedFilters,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{account: array<string, mixed>, period: array{date_start: string, date_end: string}, summary: array<string, float>, items: LengthAwarePaginator<int, object>}
     */
    public function getAccountMutations(int $storeId, string $accountId, array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $account = Account::query()
            ->forStore($storeId)
            ->whereKey($accountId)
            ->firstOrFail();

        return [
            'account' => [
                'id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'is_active' => $account->is_active,
                'label' => $account->code.' - '.$account->name,
            ],
            'period' => [
                'date_start' => $normalizedFilters['date_start'],
                'date_end' => $normalizedFilters['date_end'],
            ],
            'summary' => $this->normalizeMutationSummary(
                $this->repository->mutationSummary($storeId, $accountId, $normalizedFilters),
                $account->normal_balance,
            ),
            'items' => $this->repository->paginateMutations($storeId, $accountId, $normalizedFilters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $today = Carbon::today();
        $month = $filters['month'] ?? null;

        if ($month !== null && $month !== '') {
            $periodStart = Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth();
            $dateStart = $periodStart->toDateString();
            $dateEnd = $periodStart->endOfMonth()->toDateString();
        } else {
            $dateStart = isset($filters['date_start']) && $filters['date_start'] !== ''
                ? $this->parseDate((string) $filters['date_start'])->toDateString()
                : $today->copy()->startOfMonth()->toDateString();
            $dateEnd = isset($filters['date_end']) && $filters['date_end'] !== ''
                ? $this->parseDate((string) $filters['date_end'])->toDateString()
                : $today->toDateString();
        }

        return [
            'month' => $month !== '' ? $month : null,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'search' => isset($filters['search']) ? trim((string) $filters['search']) : null,
            'account_id' => $filters['account_id'] ?? null,
            'type' => $filters['type'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'sort_by' => $filters['sort_by'] ?? 'code',
            'sort_direction' => $filters['sort_direction'] ?? 'asc',
            'show_zero_balance' => $this->toBoolean($filters['show_zero_balance'] ?? false),
            'group_by_type' => $this->toBoolean($filters['group_by_type'] ?? true),
            'per_page' => (int) ($filters['per_page'] ?? 25),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function groupRowsIfNeeded(array $rows, array $filters): array
    {
        if (($filters['group_by_type'] ?? false) !== true) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (! isset($grouped[$type])) {
                $grouped[$type] = [
                    'type' => $type,
                    'type_label' => TrialBalanceRowResource::typeLabel($type),
                    'rows' => [],
                    'subtotal_mutation_debit' => 0.0,
                    'subtotal_mutation_credit' => 0.0,
                    'subtotal_total_debit' => 0.0,
                    'subtotal_total_credit' => 0.0,
                    'subtotal_ending_balance' => 0.0,
                ];
            }

            $grouped[$type]['rows'][] = $row;
            $grouped[$type]['subtotal_mutation_debit'] += (float) ($row['mutation_debit'] ?? 0);
            $grouped[$type]['subtotal_mutation_credit'] += (float) ($row['mutation_credit'] ?? 0);
            $grouped[$type]['subtotal_total_debit'] += (float) ($row['total_debit'] ?? 0);
            $grouped[$type]['subtotal_total_credit'] += (float) ($row['total_credit'] ?? 0);
            $grouped[$type]['subtotal_ending_balance'] += (float) ($row['ending_balance'] ?? 0);
        }

        return array_values(array_map(function (array $group): array {
            foreach (['subtotal_mutation_debit', 'subtotal_mutation_credit', 'subtotal_total_debit', 'subtotal_total_credit', 'subtotal_ending_balance'] as $key) {
                $group[$key] = round($group[$key], 2);
            }

            return $group;
        }, $grouped));
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array{total_debit: float, total_credit: float, ending_balance: float}  $summary
     * @return array{total_debit: float, total_credit: float, ending_balance: float}
     */
    protected function normalizeMutationSummary(array $summary, string $normalBalance): array
    {
        $summary['ending_balance'] = $normalBalance === 'credit'
            ? round($summary['total_credit'] - $summary['total_debit'], 2)
            : round($summary['total_debit'] - $summary['total_credit'], 2);

        return $summary;
    }

    protected function parseDate(string $value): Carbon
    {
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, trim($value));
            } catch (\Throwable) {
                continue;
            }
        }

        throw new \InvalidArgumentException('Format tanggal laporan tidak valid.');
    }
}
