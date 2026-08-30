<?php

namespace App\Repositories\Jualan;

use App\Models\Jualan\DeliveryNote;
use App\Models\Jualan\DeliveryNoteItem;
use App\Models\Jualan\Order;
use App\Models\Jualan\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DeliveryNoteRepository
{
    public function paginate(int|string $storeId, array $filters): LengthAwarePaginator
    {
        $query = DeliveryNote::with(['order.customer', 'customerOrder.customer', 'items'])
            ->where('store_id', $storeId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('delivery_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_address', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customerOrder', function ($cq) use ($search) {
                        $cq->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'semua') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type']) && $filters['source_type'] !== 'semua') {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['customer_order_id'])) {
            $query->where('customer_order_id', $filters['customer_order_id']);
        }

        if (!empty($filters['delivery_date_start'])) {
            $query->whereDate('delivery_date', '>=', $filters['delivery_date_start']);
        }

        if (!empty($filters['delivery_date_end'])) {
            $query->whereDate('delivery_date', '<=', $filters['delivery_date_end']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['created_at', 'updated_at', 'delivery_number', 'delivery_date', 'status', 'source_type', 'printed_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 20);

        return $query->paginate($perPage);
    }

    public function findDetail(int|string $storeId, int|string $id): ?DeliveryNote
    {
        return DeliveryNote::with(['order.customer', 'customerOrder.customer', 'items.product'])
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): DeliveryNote
    {
        return DeliveryNote::create($data);
    }

    public function update(DeliveryNote $deliveryNote, array $data): bool
    {
        return $deliveryNote->update($data);
    }

    public function delete(DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->delete();
    }

    public function updateStatus(DeliveryNote $deliveryNote, string $status): bool
    {
        return $deliveryNote->update(['status' => $status]);
    }

    public function generateDeliveryNumber(int|string $storeId): string
    {
        $today = Carbon::now()->format('Ymd');
        $prefix = "SJ-{$today}-";

        $latest = DeliveryNote::where('store_id', $storeId)
            ->where('delivery_number', 'like', "{$prefix}%")
            ->withTrashed()
            ->orderBy('id', 'desc')
            ->value('delivery_number');

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
        $sourceType = $filters['source_type'] ?? 'order_pelanggan';
        $limit = (int) ($filters['limit'] ?? 20);
        $search = $filters['search'] ?? null;

        $query = Order::with(['customer', 'items'])
            ->where('store_id', $storeId);

        if ($sourceType === 'order_pelanggan') {
            $query->whereIn('status', ['diproses', 'selesai']);
        } else {
            $query->where('status', 'selesai');
        }

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
            $items = $this->eligibleOrderItems($order, $sourceType);
            $hasRemaining = false;
            foreach ($items as $item) {
                if ($item['remaining_quantity'] > 0) {
                    $hasRemaining = true;
                    break;
                }
            }

            if ($hasRemaining) {
                $eligible[] = [
                    'id' => $order->id,
                    'source_type' => $sourceType,
                    'order_number' => $order->order_number ?? $order->code ?? '',
                    'customer_name' => $order->customer?->name,
                    'recipient_name' => $order->customer?->name ?? 'Pelanggan',
                    'recipient_address' => $order->shipping_address ?? $order->address ?? '-',
                    'created_at' => optional($order->created_at)->toISOString(),
                    'items' => $items,
                ];

                if (count($eligible) >= $limit) {
                    break;
                }
            }
        }

        return $eligible;
    }

    public function eligibleOrderItems(Order $order, string $sourceType, ?int $excludeDeliveryNoteId = null): array
    {
        $orderItems = OrderItem::with('product')
            ->where('order_id', $order->id)
            ->get();

        $itemIds = $orderItems->pluck('id')->toArray();
        $deliveredMap = $this->calculateDeliveredQtyByOrderItemIds($itemIds, $excludeDeliveryNoteId);

        $result = [];
        foreach ($orderItems as $item) {
            $orderedQty = (float) ($item->quantity ?? $item->qty ?? $item->qty_sold ?? 0);
            $deliveredQty = (float) ($deliveredMap[$item->id] ?? 0);
            $remainingQty = max(0, $orderedQty - $deliveredQty);

            $productCode = $item->product?->sku 
                ?? $item->product?->barcode 
                ?? $item->product?->product_code 
                ?? $item->product_code 
                ?? null;

            $result[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? $item->product?->name ?? 'Produk',
                'product_code' => $productCode,
                'ordered_quantity' => $orderedQty,
                'delivered_quantity' => $deliveredQty,
                'remaining_quantity' => $remainingQty,
            ];
        }

        return $result;
    }

    public function calculateDeliveredQtyByOrderItemIds(array $orderItemIds, ?int $excludeDeliveryNoteId = null): array
    {
        if (empty($orderItemIds)) {
            return [];
        }

        $query = DeliveryNoteItem::whereIn('order_item_id', $orderItemIds)
            ->whereHas('deliveryNote', function ($q) use ($excludeDeliveryNoteId) {
                $q->whereIn('status', ['draft', 'dikirim', 'terkirim']);
                if ($excludeDeliveryNoteId) {
                    $q->where('id', '!=', $excludeDeliveryNoteId);
                }
            });

        return $query->groupBy('order_item_id')
            ->selectRaw('order_item_id, SUM(quantity) as total_delivered')
            ->pluck('total_delivered', 'order_item_id')
            ->toArray();
    }
}