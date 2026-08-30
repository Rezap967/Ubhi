<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExpenseRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $sortColumns = [
            'expense_date' => 'expense_date',
            'description' => 'description',
            'category' => 'category_id',
            'amount' => 'total_amount',
        ];

        return Expense::query()
            ->forStore($storeId)
            ->with(['category', 'journalEntry'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', '%'.$search.'%')
                        ->orWhere('expense_no', 'like', '%'.$search.'%')
                        ->orWhere('reference_no', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, string $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy($sortColumns[$filters['sort_by'] ?? 'expense_date'] ?? 'expense_date', ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    public function findForStoreOrFail(int $storeId, string $id): Expense
    {
        return Expense::query()
            ->forStore($storeId)
            ->with(['category', 'expenseAccount', 'cashOrBankAccount', 'journalEntry', 'createdBy', 'updatedBy', 'voidedBy'])
            ->whereKey($id)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense
    {
        return Expense::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense->fresh(['category', 'expenseAccount', 'cashOrBankAccount', 'journalEntry']);
    }

    /** @return Collection<int, ExpenseCategory> */
    public function getActiveCategories(int $storeId): Collection
    {
        return ExpenseCategory::query()
            ->forStore($storeId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function getTotalCount(int $storeId, array $filters): int
    {
        return $this->paginateForStore($storeId, $filters)->total();
    }
}
