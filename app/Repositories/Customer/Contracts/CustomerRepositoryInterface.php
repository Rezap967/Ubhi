<?php

namespace App\Repositories\Customer\Contracts;

use App\Models\Customer\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function paginateByStore(int $storeId, array $filters = []): LengthAwarePaginator;

    public function findByIdAndStore(int $id, int $storeId): Customer;

    public function existsPhone(int $storeId, ?string $phone, ?int $ignoreId = null): bool;

    public function existsEmail(int $storeId, ?string $email, ?int $ignoreId = null): bool;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function softDelete(Customer $customer, int $userId): bool;

    public function updateStatus(Customer $customer, string $status, int $userId): Customer;

    public function getLastCodeNumberForStoreWithLock(int $storeId): int;
}
