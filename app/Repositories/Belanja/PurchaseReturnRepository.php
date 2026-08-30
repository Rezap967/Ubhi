<?php

namespace App\Repositories\Belanja;

use App\Models\Belanja\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReturnRepository
{
    protected array $sortable = ['return_number', 'status', 'total', 'created_at'];

    public function paginate(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = PurchaseReturn::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number'])
            ->where('store_id', $storeId);

        // Search: return_number, supplier name, atau PO number
        if (! empty($filters['search'])) {
            $search = '%'.mb_strtolower($filters['search']).'%';
            $query->where(function (Builder $q) use ($search) {
                $q->whereRaw('LOWER(return_number) LIKE ?', [$search])
                    ->orWhereHas('supplier', function (Builder $sq) use ($search) {
                        $sq->whereRaw('LOWER(name) LIKE ?', [$search]);
                    })
                    ->orWhereHas('purchaseOrder', function (Builder $pq) use ($search) {
                        $pq->whereRaw('LOWER(po_number) LIKE ?', [$search]);
                    });
            });
        }

        // Status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Sorting
        $sortBy = in_array($filters['sort_by'] ?? null, $this->sortable, true)
            ? $filters['sort_by']
            : 'created_at';

        $sortDirection = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return $query->paginate($perPage);
    }

    public function findById(int $storeId, int $id): ?PurchaseReturn
    {
        return PurchaseReturn::query()
            ->where('store_id', $storeId)
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number,status', 'items.product:id,name,sku', 'items.purchaseOrderItem'])
            ->find($id);
    }
}
