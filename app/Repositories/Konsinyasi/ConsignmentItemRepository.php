<?php

namespace App\Repositories\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentItem;
use App\Repositories\Konsinyasi\Contracts\ConsignmentItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsignmentItemRepository implements ConsignmentItemRepositoryInterface
{
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->with(['product', 'supplier', 'defaultWarehouse', 'stockBalances']);

        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('product', function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('default_warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['created_at', 'selling_price', 'consignment_price', 'status'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function findForStoreOrFail(int $storeId, int $id): ConsignmentItem
    {
        return ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->with(['product', 'supplier', 'defaultWarehouse', 'stockBalances', 'stockLedgers' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->findOrFail($id);
    }

    public function create(array $data): ConsignmentItem
    {
        return ConsignmentItem::query()->create($data);
    }

    public function update(ConsignmentItem $item, array $data): ConsignmentItem
    {
        $item->update($data);

        return $item->fresh(['product', 'supplier', 'warehouse', 'stockBalances']);
    }

    public function existsDuplicate(int $storeId, int $productId, int $supplierId, ?int $exceptId = null): bool
    {
        $query = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at');

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function getSummaryStatistics(int $storeId): array
    {
        $base = ConsignmentItem::query()->where('store_id', $storeId);

        return [
            'total_items' => (clone $base)->count(),
            'active_items' => (clone $base)->where('status', 'active')->count(),
            'inactive_items' => (clone $base)->where('status', 'inactive')->count(),
            'closed_items' => (clone $base)->where('status', 'closed')->count(),
            'total_stock_value' => (float) DB::table('consignment_stock_balances')
                ->join('consignment_items', 'consignment_items.id', '=', 'consignment_stock_balances.consignment_item_id')
                ->where('consignment_stock_balances.store_id', $storeId)
                ->selectRaw('COALESCE(SUM(consignment_stock_balances.quantity_available * consignment_items.consignment_price), 0) as total')
                ->value('total'),
        ];
    }

    public function getBySupplierForStore(int $storeId, int $supplierId): Collection
    {
        return ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->with('product')
            ->get();
    }
}