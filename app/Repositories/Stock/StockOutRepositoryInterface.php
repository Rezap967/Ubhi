<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockOut;

interface StockOutRepositoryInterface
{
    public function create(array $data): StockOut;
}
