<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockOut;

class StockOutRepository implements StockOutRepositoryInterface
{
    public function create(array $data): StockOut
    {
        return StockOut::create($data);
    }
}
