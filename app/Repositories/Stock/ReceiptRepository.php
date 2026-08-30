<?php

namespace App\Repositories\Stock;

use App\Models\Stock\StockIn;
use Illuminate\Database\Eloquent\Collection;

class ReceiptRepository implements ReceiptRepositoryInterface
{
    public function create(array $data): StockIn
    {
        return StockIn::create($data);
    }

    public function getAll(int $storeId, array $filters = []): Collection
    {
        $query = StockIn::with(['product', 'warehouse'])
            ->where('store_id', $storeId)
            ->orderBy('received_date', 'desc');

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($query) use ($keyword) {
                $query->where('reference_no', 'like', "%{$keyword}%")
                    ->orWhereHas('product', function ($query) use ($keyword) {
                        $query->where('name', 'like', "%{$keyword}%");
                    });
            });
        }

        return $query->get();
    }

    public function findByStoreOrFail(int $storeId, int $id): StockIn
    {
        return StockIn::where('store_id', $storeId)
            ->where('id', $id)
            ->firstOrFail();
    }

    public function delete(StockIn $receipt): bool
    {
        return $receipt->delete();
    }
}
