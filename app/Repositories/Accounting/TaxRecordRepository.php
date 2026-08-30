<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\TaxRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TaxRecordRepository
{
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $sort = ['transaction_no', 'tax_date', 'tax_type', 'tax_base', 'tax_amount', 'status'];
        $column = in_array($filters['sort_by'] ?? 'tax_date', $sort, true) ? $filters['sort_by'] : 'tax_date';
        $direction = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return TaxRecord::query()->forStore($storeId)->with(['taxRate', 'taxAccount', 'counterAccount', 'journalEntry'])->when(($filters['status'] ?? 'all') !== 'all', fn (Builder $q) => $q->where('status', $filters['status']))->when($filters['search'] ?? null, function (Builder $q, string $s): void {
            $q->where(fn (Builder $x) => $x->where('transaction_no', 'like', "%$s%")->orWhere('tax_name', 'like', "%$s%")->orWhere('reference_no', 'like', "%$s%"));
        })->orderBy($column, $direction)->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
    }

    public function findForStoreOrFail(int $storeId, string $id): TaxRecord
    {
        return TaxRecord::query()->forStore($storeId)->with(['taxRate', 'taxAccount', 'counterAccount', 'journalEntry.lines.account'])->whereKey($id)->firstOrFail();
    }

    public function create(array $data): TaxRecord
    {
        return TaxRecord::query()->create($data);
    }

    public function update(TaxRecord $record, array $data): TaxRecord
    {
        $record->update($data);

        return $record->fresh(['taxRate', 'taxAccount', 'counterAccount', 'journalEntry']);
    }

    public function summary(int $storeId): array
    {
        $q = TaxRecord::query()->forStore($storeId)->where('status', '!=', 'cancelled');

        return ['ppn_amount' => (float) (clone $q)->whereIn('tax_type', ['ppn_keluaran', 'ppn_masukan'])->sum('tax_amount'), 'pph_final_amount' => (float) (clone $q)->where('tax_type', 'pph_final_umkm')->sum('tax_amount'), 'total_tax_amount' => (float) (clone $q)->sum('tax_amount')];
    }
}
