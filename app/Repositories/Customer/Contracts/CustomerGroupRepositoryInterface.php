<?php

namespace App\Repositories\Customer\Contracts;

use App\Models\Customer\CustomerGroup;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerGroupRepositoryInterface
{
    public function paginate(int $storeId, array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findById(int $id, int $storeId): ?CustomerGroup;

    public function create(array $data): CustomerGroup;

    public function update(CustomerGroup $group, array $data): CustomerGroup;

    public function delete(CustomerGroup $group): bool;

    public function countActiveCustomers(CustomerGroup $group, int $storeId): int;
}
