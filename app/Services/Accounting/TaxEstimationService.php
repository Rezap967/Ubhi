<?php

namespace App\Services\Accounting;

use App\Repositories\Accounting\TaxEstimationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TaxEstimationService
{
    public function __construct(protected TaxEstimationRepository $repository) {}

    /** @return array{rows: LengthAwarePaginator, summary: array<string, mixed>, filters: array<string, mixed>, period: array<string, mixed>} */
    public function getPreview(int|string $storeId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $summary = $this->repository->getSummary($storeId, $normalized);

        return [
            'rows' => $this->repository->getGroupedEstimation($storeId, $normalized),
            'summary' => [
                ...$summary,
                'ppn_amount_label' => $this->formatCurrency($summary['ppn_amount']),
                'ppn_subtitle' => 'Dari '.$summary['ppn_count'].' transaksi',
                'pph_final_amount_label' => $this->formatCurrency($summary['pph_final_amount']),
                'pph_final_subtitle' => 'Dari '.$summary['pph_final_count'].' transaksi',
                'total_tax_amount_label' => $this->formatCurrency($summary['total_tax_amount']),
                'total_subtitle' => 'Total dari semua pajak',
                'total_tax_base_label' => $this->formatCurrency($summary['total_tax_base']),
            ],
            'filters' => $normalized,
            'period' => [
                'label' => $this->periodLabel($normalized),
                'date_start' => $normalized['date_start'],
                'date_end' => $normalized['date_end'],
                'month' => $normalized['month'],
            ],
        ];
    }

    /** @return array{status: string, period: string|null, month: string|null, date_start: string|null, date_end: string|null, page: int, per_page: int} */
    public function normalizeFilters(array $filters): array
    {
        $period = isset($filters['period']) && trim((string) $filters['period']) !== '' ? trim((string) $filters['period']) : null;
        $month = $filters['month'] ?? null;
        $dateStart = $this->parseDate($filters['date_start'] ?? null, 'date_start');
        $dateEnd = $this->parseDate($filters['date_end'] ?? null, 'date_end');

        if (! $month && $period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $month = $period;
        }

        if (! $month && $period && str_contains($period, ' - ')) {
            [$periodStart, $periodEnd] = array_pad(explode(' - ', $period, 2), 2, null);
            $dateStart ??= $this->parseDate($periodStart, 'date_start');
            $dateEnd ??= $this->parseDate($periodEnd, 'date_end');
        }

        if ($month !== null) {
            try {
                Carbon::createFromFormat('!Y-m', $month);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['month' => 'Format periode tidak valid.']);
            }
        }

        if ($dateStart && $dateEnd && $dateEnd < $dateStart) {
            throw ValidationException::withMessages(['date_end' => 'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.']);
        }

        return [
            'status' => $filters['status'] ?? 'all',
            'period' => $period,
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($filters['per_page'] ?? 10))),
        ];
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

        throw ValidationException::withMessages([$field => $field === 'date_end' ? 'Tanggal selesai tidak valid.' : 'Tanggal mulai tidak valid.']);
    }

    public function formatCurrency(float|int $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function taxTypeLabel(string $type): string
    {
        return [
            'ppn_keluaran' => 'PPN Keluaran',
            'ppn_masukan' => 'PPN Masukan',
            'pph_final_umkm' => 'PPh Final UMKM',
            'pph' => 'PPh',
            'other' => 'Lainnya',
        ][$type] ?? $type;
    }

    /** @param array{month: string|null, date_start: string|null, date_end: string|null} $filters */
    public function periodLabel(array $filters): string
    {
        if ($filters['month']) {
            $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
            $month = Carbon::createFromFormat('!Y-m', $filters['month']);

            return $monthNames[$month->month].' '.$month->year;
        }

        if ($filters['date_start'] || $filters['date_end']) {
            return ($filters['date_start'] ? $this->formatDateLabel($filters['date_start']) : 'Awal')
                .' - '.($filters['date_end'] ? $this->formatDateLabel($filters['date_end']) : 'Sekarang');
        }

        return 'Belum ada periode';
    }

    protected function formatDateLabel(string $date): string
    {
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $parsed = Carbon::parse($date);

        return $parsed->day.' '.$monthNames[$parsed->month].' '.$parsed->year;
    }
}
