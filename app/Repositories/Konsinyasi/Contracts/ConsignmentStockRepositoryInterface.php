<?php

namespace App\Repositories\Konsinyasi\Contracts;

use App\Models\Konsinyasi\ConsignmentStockBalance;

interface ConsignmentStockRepositoryInterface
{
    public function upsertInitialBalance(array $data): ConsignmentStockBalance;

    public function recordLedger(array $data): \App\Models\Konsinyasi\ConsignmentStockLedger;

    public function getBalanceForItem(int $storeId, int $consignmentItemId, ?int $warehouseId = null): ?ConsignmentStockBalance;

    public function getLedgerHistory(int $storeId, int $consignmentItemId): \Illuminate\Support\Collection;
}