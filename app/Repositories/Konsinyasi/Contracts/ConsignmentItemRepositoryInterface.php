<?php

namespace App\Repositories\Konsinyasi\Contracts;

use App\Models\Konsinyasi\ConsignmentItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConsignmentItemRepositoryInterface
{
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator;

    public function findForStoreOrFail(int $storeId, int $id): ConsignmentItem;

    public function create(array $data): ConsignmentItem;

    public function update(ConsignmentItem $item, array $data): ConsignmentItem;

    public function existsDuplicate(int $storeId, int $productId, int $supplierId, ?int $exceptId = null): bool;

    public function getSummaryStatistics(int $storeId): array;

    public function getBySupplierForStore(int $storeId, int $supplierId): \Illuminate\Support\Collection;
}