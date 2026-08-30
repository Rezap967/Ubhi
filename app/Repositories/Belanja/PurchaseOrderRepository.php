<?php

namespace App\Repositories\Belanja;

use App\Models\Belanja\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderRepository
{
    protected array $sortable = ['po_number', 'status', 'total', 'created_at'];

    public function paginate(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier:id,name'])
            ->where('store_id', $storeId);

        // Search: po_number atau nama supplier
        if (! empty($filters['search'])) {
            $search = '%'.mb_strtolower($filters['search']).'%';
            $query->where(function (Builder $q) use ($search) {
                $q->whereRaw('LOWER(po_number) LIKE ?', [$search])
                    ->orWhereHas('supplier', function (Builder $sq) use ($search) {
                        $sq->whereRaw('LOWER(name) LIKE ?', [$search]);
                    });
            });
        }

        // Status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            $statusMap = [
                'Submitted' => 'Dikirim',
                'Partial' => 'Sebagian',
                'Received' => 'Diterima',
                'Cancelled' => 'Dibatalkan',
            ];
            $status = $statusMap[$status] ?? $status;
            $query->where('status', $status);
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

    public function findById(int $storeId, int $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->where('store_id', $storeId)
            ->with(['supplier:id,name', 'items.product:id,name,sku'])
            ->find($id);
    }
}
