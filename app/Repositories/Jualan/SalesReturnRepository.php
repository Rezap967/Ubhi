<?php

namespace App\Repositories\Jualan;

use App\Models\Customer\Customer;
use App\Models\Jualan\Order;
use App\Models\Jualan\OrderItem;
use App\Models\Jualan\SalesReturn;
use App\Models\Jualan\SalesReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class SalesReturnRepository
{
    public function paginate(int|string $storeId, array $filters): LengthAwarePaginator
    {
        $query = SalesReturn::with(['order.customer', 'customer', 'approvedBy'])
            ->where('store_id', $storeId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'semua') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['refund_method']) && $filters['refund_method'] !== 'semua') {
            $query->where('refund_method', $filters['refund_method']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_start'])) {
            $query->whereDate('created_at', '>=', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $query->whereDate('created_at', '<=', $filters['date_end']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? $filters['sort_order'] ?? 'desc';
        
        $allowedSorts = ['created_at', 'updated_at', 'return_number', 'total_refund', 'status', 'refund_method', 'approved_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 20);

        return $query->paginate($perPage);
    }

    public function findDetail(int|string $storeId, int|string $id): ?SalesReturn
    {
        return SalesReturn::with(['order.customer', 'customer', 'approvedBy', 'items.product'])
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): SalesReturn
    {
        return SalesReturn::create($data);
    }

    public function updateStatus(SalesReturn $salesReturn, string $status, ?int $approvedBy = null): bool
    {
        $payload = ['status' => $status];
        if ($status === 'disetujui') {
            $payload['approved_by'] = $approvedBy;
            $payload['approved_at'] = Carbon::now();
        }

        return $salesReturn->update($payload);
    }

    public function generateReturnNumber(int|string $storeId): string
    {
        $today = Carbon::now()->format('Ymd');
        $prefix = "SRET-{$today}-";

        $latest = SalesReturn::where('store_id', $storeId)
            ->where('return_number', 'like', "{$prefix}%")
            ->withTrashed()
            ->orderBy('id', 'desc')
            ->value('return_number');

        if ($latest) {
            $number = (int) substr($latest, -4);
            $nextNumber = str_pad($number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . $nextNumber;
    }

    public function eligibleOrders(int|string $storeId, array $filters): array
    {
        $limit = (int) ($filters['limit'] ?? 20);
        $search = $filters['search'] ?? null;

        $query = Order::with(['customer', 'items'])
            ->where('store_id', $storeId)
            ->where('status', 'selesai');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->latest()->limit(50)->get();

        $eligible = [];
        foreach ($orders as $order) {
            $returnableItems = $this->returnableItems($order);
            $hasReturnable = false;
            foreach ($returnableItems as $item) {
                if ($item['returnable_qty'] > 0) {
                    $hasReturnable = true;
                    break;
                }
            }

            if ($hasReturnable) {
                $customerCode = $order->customer?->code ?? $order->customer?->customer_code ?? null;
                $eligible[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?? $order->code ?? '',
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->name,
                        'code' => $customerCode,
                        'phone' => $order->customer->phone ?? null,
                    ] : null,
                    'total' => (float) ($order->total ?? $order->grand_total ?? 0),
                    'status' => $order->status,
                    'created_at' => optional($order->created_at)->toISOString(),
                ];

                if (count($eligible) >= $limit) {
                    break;
                }
            }
        }

        return $eligible;
    }

    public function returnableItems(Order $order): array
    {
        $orderItems = OrderItem::with('product')
            ->where('order_id', $order->id)
            ->get();

        $itemIds = $orderItems->pluck('id')->toArray();
        $returnedMap = $this->calculateReturnedQtyByOrderItemIds($itemIds);

        $result = [];
        foreach ($orderItems as $item) {
            $qtySold = (float) ($item->quantity ?? $item->qty ?? $item->qty_sold ?? 0);
            $qtyReturnedBefore = (float) ($returnedMap[$item->id] ?? 0);
            $returnableQty = max(0, $qtySold - $qtyReturnedBefore);

            $productCode = $item->product?->sku 
                ?? $item->product?->barcode 
                ?? $item->product?->product_code 
                ?? $item->product_code 
                ?? null;

            $unitPrice = (float) ($item->unit_price ?? $item->price ?? $item->sell_price ?? 0);

            $result[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? $item->product?->name ?? 'Produk',
                'product_code' => $productCode,
                'qty_sold' => $qtySold,
                'qty_returned_before' => $qtyReturnedBefore,
                'returnable_qty' => $returnableQty,
                'unit_price' => $unitPrice,
            ];
        }

        return $result;
    }

    public function calculateReturnedQtyByOrderItemIds(array $orderItemIds): array
    {
        if (empty($orderItemIds)) {
            return [];
        }

        return SalesReturnItem::whereIn('order_item_id', $orderItemIds)
            ->whereHas('salesReturn', function ($q) {
                $q->whereIn('status', ['pending', 'disetujui']);
            })
            ->groupBy('order_item_id')
            ->selectRaw('order_item_id, SUM(qty_returned) as total_returned')
            ->pluck('total_returned', 'order_item_id')
            ->toArray();
    }
}