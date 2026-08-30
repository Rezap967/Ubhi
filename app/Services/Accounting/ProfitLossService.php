<?php

namespace App\Services\Accounting;

use App\Repositories\Accounting\ProfitLossRepository;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProfitLossService
{
    public function __construct(protected ProfitLossRepository $repository) {}

    /** @return array<string, mixed> */
    public function getReport(int $storeId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $revenues = $this->repository->getRevenueAccounts($storeId, $normalized);
        $expenses = $this->repository->getExpenseAccounts($storeId, $normalized);
        $totalRevenue = round($revenues->sum(fn (object $row): float => (float) $row->amount), 2);
        $totalExpense = round($expenses->sum(fn (object $row): float => (float) $row->amount), 2);
        $netProfit = round($totalRevenue - $totalExpense, 2);

        return [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_revenue_label' => $this->formatCurrency($totalRevenue),
                'total_expense' => $totalExpense,
                'total_expense_label' => $this->formatCurrency($totalExpense),
                'net_profit' => $netProfit,
                'net_profit_label' => $this->formatCurrency($netProfit),
                'is_profit' => $netProfit >= 0,
                'total_data' => $revenues->count() + $expenses->count(),
            ],
            'filters' => $normalized,
            'period' => [
                'date_start' => $normalized['date_start'],
                'date_end' => $normalized['date_end'],
            ],
        ];
    }

    /** @return array{date_start: string, date_end: string} */
    public function normalizeFilters(array $filters): array
    {
        $today = Carbon::today();
        $dateStart = $this->parseDate($filters['date_start'] ?? null, 'date_start') ?? $today->copy()->startOfMonth()->toDateString();
        $dateEnd = $this->parseDate($filters['date_end'] ?? null, 'date_end') ?? $today->toDateString();

        if ($dateEnd < $dateStart) {
            throw ValidationException::withMessages([
                'date_end' => 'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.',
            ]);
        }

        return ['date_start' => $dateStart, 'date_end' => $dateEnd];
    }

    public function parseDate(?string $value, string $field = 'date_start'): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, trim($value))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            $field => $field === 'date_end'
                ? 'Tanggal selesai tidak valid.'
                : 'Tanggal mulai tidak valid.',
        ]);
    }

    public function formatCurrency(float|int $amount): string
    {
        $amount = (float) $amount;

        return $amount < 0
            ? '-Rp '.number_format(abs($amount), 0, ',', '.')
            : 'Rp '.number_format($amount, 0, ',', '.');
    }
}
