<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\CustomerReceivable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerReceivableRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $sortColumns = [
            'customer_name' => 'customer_name',
            'status' => 'status',
            'due_date' => 'due_date',
            'outstanding_amount' => 'outstanding_amount',
        ];
        $direction = ($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return CustomerReceivable::query()
            ->forStore($storeId)
            ->with(['customer', 'payments', 'receivableAccount', 'revenueAccount', 'journalEntry'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('receivable_no', 'like', "%{$search}%")
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

    public function findForStoreOrFail(int $storeId, string $id): CustomerReceivable
    {
        return CustomerReceivable::query()
            ->forStore($storeId)
            ->with(['customer', 'payments.cashOrBankAccount', 'receivableAccount', 'revenueAccount', 'journalEntry.lines.account'])
            ->whereKey($id)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): CustomerReceivable
    {
        return CustomerReceivable::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(CustomerReceivable $receivable, array $data): CustomerReceivable
    {
        $receivable->update($data);

        return $receivable->fresh(['customer', 'payments', 'receivableAccount', 'revenueAccount', 'journalEntry']);
    }

    public function summary(int $storeId): array
    {
        $query = CustomerReceivable::query()->forStore($storeId);
        $overdue = (clone $query)->where('status', '!=', 'void')->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today());

        return [
            'total_receivable' => (float) (clone $query)->where('status', '!=', 'void')->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
            'overdue_amount' => (float) $overdue->sum('outstanding_amount'),
            'overdue_count' => (int) $overdue->count(),
        ];
    }
}
