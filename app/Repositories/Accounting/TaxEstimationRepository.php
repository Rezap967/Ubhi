<?php

namespace App\Repositories\Accounting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaxEstimationRepository
{
    /** @param array{status?: string, month?: string|null, date_start?: string|null, date_end?: string|null, per_page?: int} $filters */
    public function getGroupedEstimation(int|string $storeId, array $filters): LengthAwarePaginator
    {
        return $this->baseQuery($storeId, $filters)
            ->selectRaw('tr.tax_type, MAX(tr.tax_name) as tax_type_label, SUM(tr.tax_base) as total_tax_base, AVG(tr.tax_rate) as average_tax_rate, SUM(tr.tax_amount) as total_tax_amount, COUNT(*) as transaction_count')
            ->groupBy('tr.tax_type')
            ->orderBy('tr.tax_type')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    /** @param array{status?: string, month?: string|null, date_start?: string|null, date_end?: string|null} $filters */
    /** @return array<string, float|int> */
    public function getSummary(int|string $storeId, array $filters): array
    {
        $query = $this->baseQuery($storeId, $filters);
        $ppn = (clone $query)->whereIn('tr.tax_type', ['ppn_keluaran', 'ppn_masukan']);
        $pphFinal = (clone $query)->where('tr.tax_type', 'pph_final_umkm');

        return [
            'ppn_amount' => (float) $ppn->sum('tr.tax_amount'),
            'ppn_base' => (float) $ppn->sum('tr.tax_base'),
            'ppn_count' => (int) $ppn->count(),
            'pph_final_amount' => (float) $pphFinal->sum('tr.tax_amount'),
            'pph_final_base' => (float) $pphFinal->sum('tr.tax_base'),
            'pph_final_count' => (int) $pphFinal->count(),
            'total_tax_amount' => (float) (clone $query)->sum('tr.tax_amount'),
            'total_tax_base' => (float) (clone $query)->sum('tr.tax_base'),
            'total_transaction_count' => (int) (clone $query)->count(),
        ];
    }

    /** @param array{status?: string, month?: string|null, date_start?: string|null, date_end?: string|null} $filters */
    public function baseQuery(int|string $storeId, array $filters): Builder
    {
        $query = DB::table('tax_records as tr')
            ->where('tr.store_id', $storeId)
            ->whereNull('tr.deleted_at');

        $status = $filters['status'] ?? 'all';
        if ($status === 'all') {
            $query->where('tr.status', '!=', 'cancelled');
        } elseif ($status !== '') {
            $query->where('tr.status', $status);
        }

        if (! empty($filters['month'])) {
            $month = Carbon::createFromFormat('!Y-m', $filters['month']);
            $query->whereBetween('tr.tax_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        } else {
            $query->when($filters['date_start'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('tr.tax_date', '>=', $date))
                ->when($filters['date_end'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('tr.tax_date', '<=', $date));
        }

        return $query;
    }
}
