<?php

namespace App\Repositories\Customer\Eloquent;

use App\Models\Customer\CustomerGroup;
use App\Repositories\Customer\Contracts\CustomerGroupRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerGroupRepository implements CustomerGroupRepositoryInterface
{
    public function paginate(int $storeId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = CustomerGroup::query()
            ->where('store_id', $storeId)
            ->withCount(['activeCustomers as customer_count']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function findById(int $id, int $storeId): ?CustomerGroup
    {
        return CustomerGroup::where('store_id', $storeId)
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): CustomerGroup
    {
        return CustomerGroup::create($data);
    }

    public function update(CustomerGroup $group, array $data): CustomerGroup
    {
        $group->update($data);

        return $group;
    }

    public function delete(CustomerGroup $group): bool
    {
        return (bool) $group->delete();
    }

    public function countActiveCustomers(CustomerGroup $group, int $storeId): int
    {
        return $group->activeCustomers()
            ->where('store_id', $storeId)
            ->count();
    }
}
