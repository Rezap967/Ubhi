<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\RecurringExpense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecurringExpenseRepository
{
    public function getPaginated(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = RecurringExpense::with('category')
            ->where('store_id', $storeId);

        if (! empty($filters['keyword'])) {
            $query->where('name', 'like', '%'.$filters['keyword'].'%');
        }

        if (! empty($filters['expense_category_id'])) {
            $query->where('expense_category_id', $filters['expense_category_id']);
        }

        if (! empty($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 10;

        return $query->latest()->paginate($limit);
    }

    public function findByIdAndStore(int $id, int $storeId): ?RecurringExpense
    {
        return RecurringExpense::with('category')
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->first();
    }
}
