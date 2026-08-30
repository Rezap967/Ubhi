<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockIn;
use Illuminate\Database\Eloquent\Collection;

interface ReceiptRepositoryInterface
{
    public function create(array $data): StockIn;

    public function getAll(int $storeId, array $filters = []): Collection;

    public function findByStoreOrFail(int $storeId, int $id): StockIn;

    public function delete(StockIn $receipt): bool;
}
