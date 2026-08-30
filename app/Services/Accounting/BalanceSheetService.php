<?php

namespace App\Services\Accounting;

use App\Repositories\Accounting\BalanceSheetRepository;
use Carbon\Carbon;

class BalanceSheetService
{
    public function __construct(protected BalanceSheetRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReport(int $storeId, array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $asOfDate = $normalizedFilters['as_of_date'];

        // Get accounts by type
        $assets = $this->repository->getAssetAccounts($storeId, $normalizedFilters);
        $liabilities = $this->repository->getLiabilityAccounts($storeId, $normalizedFilters);
        $equities = $this->repository->getEquityAccounts($storeId, $normalizedFilters);

        // Get running profit
        $runningProfit = $this->repository->getRunningProfit($storeId, $normalizedFilters);

        // Calculate totals
        $totalAssets = (float) array_sum(array_map(fn ($a) => $a['amount'] ?? 0, $assets));
        $totalLiabilities = (float) array_sum(array_map(fn ($l) => $l['amount'] ?? 0, $liabilities));
        $totalEquity = (float) array_sum(array_map(fn ($e) => $e['amount'] ?? 0, $equities));

        $totalAssets = round($totalAssets, 2);
        $totalLiabilities = round($totalLiabilities, 2);
        $totalEquity = round($totalEquity, 2);

        $totalEquityWithProfit = round($totalEquity + $runningProfit, 2);
        $totalPasiva = round($totalLiabilities + $totalEquityWithProfit, 2);
        $difference = round($totalAssets - $totalPasiva, 2);
        $isBalanced = round($totalAssets, 2) === round($totalPasiva, 2);

        $totalData = count($assets) + count($liabilities) + count($equities);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equities' => $equities,
            'running_profit' => [
                'name' => 'Laba (Rugi) Periode Berjalan',
                'code' => null,
                'amount' => $runningProfit,
                'amount_label' => self::formatCurrency($runningProfit),
            ],
            'summary' => [
                'total_assets' => $totalAssets,
                'total_assets_label' => self::formatCurrency($totalAssets),
                'total_liabilities' => $totalLiabilities,
                'total_liabilities_label' => self::formatCurrency($totalLiabilities),
                'total_equity' => $totalEquity,
                'total_equity_label' => self::formatCurrency($totalEquity),
                'running_profit' => $runningProfit,
                'running_profit_label' => self::formatCurrency($runningProfit),
                'total_equity_with_profit' => $totalEquityWithProfit,
                'total_equity_with_profit_label' => self::formatCurrency($totalEquityWithProfit),
                'total_pasiva' => $totalPasiva,
                'total_pasiva_label' => self::formatCurrency($totalPasiva),
                'difference' => $difference,
                'difference_label' => self::formatCurrency($difference),
                'is_balanced' => $isBalanced,
                'total_data' => $totalData,
            ],
            'filters' => [
                'as_of_date' => $asOfDate,
                'date_end' => $asOfDate,
            ],
            'period' => [
                'as_of_date' => $asOfDate,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function normalizeFilters(array $filters): array
    {
        // Determine as_of_date from filters
        $asOfDateInput = $filters['as_of_date'] ?? null;
        $dateEndInput = $filters['date_end'] ?? null;

        $asOfDate = null;

        if (! empty($asOfDateInput)) {
            $asOfDate = $this->parseDate($asOfDateInput);
        } elseif (! empty($dateEndInput)) {
            $asOfDate = $this->parseDate($dateEndInput);
        } else {
            $asOfDate = now()->toDateString();
        }

        return [
            'as_of_date' => $asOfDate ?? now()->toDateString(),
        ];
    }

    public function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Try to parse as YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                Carbon::createFromFormat('Y-m-d', $value);

                return $value;
            } catch (\Exception) {
                return null;
            }
        }

        // Try to parse as DD/MM/YYYY
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            try {
                $parsed = Carbon::createFromFormat('d/m/Y', $value);

                return $parsed->toDateString();
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    public static function formatCurrency(float|int $amount): string
    {
        if ($amount < 0) {
            return '-Rp '.number_format(abs($amount), 0, ',', '.');
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
