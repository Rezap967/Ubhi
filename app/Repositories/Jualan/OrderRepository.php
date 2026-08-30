<?php

namespace App\Repositories\Jualan;

use App\Models\Jualan\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class OrderRepository
{
    public function paginate(array $filters, int|string $storeId): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['customer', 'warehouse'])
            ->where('store_id', $storeId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'semua') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'semua') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['delivery_method']) && $filters['delivery_method'] !== 'semua') {
            $query->where('delivery_method', $filters['delivery_method']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'updated_at', 'order_number', 'total', 'due_date', 'status', 'payment_status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 20);

        return $query->paginate($perPage);
    }

    public function findDetail(int|string $id, int|string $storeId): ?Order
    {
        return Order::query()
            ->with(['customer', 'warehouse', 'items.product'])
            ->where('store_id', $storeId)
            ->find($id);
    }

    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order;
    }

    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    public function generateOrderNumber(int|string $storeId): string
    {
        $today = Carbon::now()->format('Ymd');
        $prefix = "ORD-{$today}-";

        $lastOrder = Order::where('store_id', $storeId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastOrder) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($lastOrder->order_number, -4);
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return $prefix . $nextNumber;
    }
}