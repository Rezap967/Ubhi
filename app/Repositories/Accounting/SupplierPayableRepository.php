<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\SupplierPayable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SupplierPayableRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $sortColumns = [
            'supplier_name' => 'supplier_name', 'status' => 'status',
            'due_date' => 'due_date', 'outstanding_amount' => 'outstanding_amount',
        ];
        $direction = ($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return SupplierPayable::query()
            ->forStore($storeId)
            ->with(['supplier', 'payments', 'liabilityAccount', 'expenseOrInventoryAccount', 'journalEntry'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('payable_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('payments', fn (Builder $payment) => $payment->where('reference_no', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', function (Builder $query) use ($filters): void {
                match ($filters['status']) {
                    'open' => $query->where('status', '!=', 'void')->where('paid_amount', 0)->where('outstanding_amount', '>', 0)->whereDate('due_date', '>=', today()),
                    'partial' => $query->where('status', '!=', 'void')->where('paid_amount', '>', 0)->where('outstanding_amount', '>', 0)->whereDate('due_date', '>=', today()),
                    'paid' => $query->where('outstanding_amount', '<=', 0),
                    'overdue' => $query->where('status', '!=', 'void')->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today()),
                    'void' => $query->where('status', 'void'),
                    default => null,
                };
            })
            ->orderBy($sortColumns[$filters['sort_by'] ?? 'due_date'] ?? 'due_date', $direction)
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    public function findForStoreOrFail(int $storeId, string $id): SupplierPayable
    {
        return SupplierPayable::query()->forStore($storeId)
            ->with(['supplier', 'payments.cashOrBankAccount', 'liabilityAccount', 'expenseOrInventoryAccount', 'journalEntry.lines.account'])
            ->whereKey($id)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SupplierPayable
    {
        return SupplierPayable::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SupplierPayable $payable, array $data): SupplierPayable
    {
        $payable->update($data);

        return $payable->fresh(['supplier', 'payments', 'liabilityAccount', 'expenseOrInventoryAccount', 'journalEntry']);
    }

    public function summary(int $storeId): array
    {
        $query = SupplierPayable::query()->forStore($storeId);

        return [
            'total_debt' => (float) (clone $query)->where('status', '!=', 'void')->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
            'overdue_amount' => (float) (clone $query)->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today())->where('status', '!=', 'void')->sum('outstanding_amount'),
            'overdue_count' => (clone $query)->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today())->where('status', '!=', 'void')->count(),
        ];
    }
}
