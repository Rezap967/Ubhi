<?php

namespace App\Services\Jualan\Orders;

use App\Models\Jualan\Order;
use App\Models\Products\Product;
use App\Models\Stock\StockMovement;
use App\Repositories\Jualan\OrderRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    protected function adjustStockForOrderItems(Order $order, array $items, string $direction): void
    {
        foreach ($items as $item) {
            $product = Product::where('store_id', $order->store_id)
                ->where('id', $item['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (float) ($item['quantity'] ?? 0);

            $before = (float) ($product->initial_stock ?? 0);

            if ($direction === 'out' && $before < $qty) {
                throw new Exception("Stok tidak mencukupi untuk produk {$product->name}.");
            }

            $after = $direction === 'out'
                ? max(0, $before - $qty)
                : $before + $qty;

            $product->update([
                'initial_stock' => $after,
            ]);

            StockMovement::create([
                'store_id' => $order->store_id,
                'product_id' => $product->id,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'movement_type' => $direction === 'out' ? 'stock_out' : 'stock_in',
                'quantity' => $qty,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'notes' => $direction === 'out'
                    ? 'Order pelanggan: '.$order->order_number
                    : 'Pembatalan order pelanggan: '.$order->order_number,
                'movement_at' => now(),
                'created_by' => $order->created_by,
            ]);
        }
    }

    public function createOrder(array $payload, int|string $storeId, int|string $userId): Order
    {
        return DB::transaction(function () use ($payload, $storeId, $userId) {
            $orderNumber = $this->orderRepository->generateOrderNumber($storeId);

            $subtotal = 0;
            $discountTotal = 0;
            $itemsData = [];

            foreach ($payload['items'] as $item) {
                $product = Product::where('store_id', $storeId)->findOrFail($item['product_id']);

                $unitPrice = (float) ($product->sell_price ?? $product->price ?? 0);
                $quantity = (float) $item['quantity'];
                $discountValue = (float) ($item['discount_value'] ?? 0);

                $lineSubtotal = ($unitPrice * $quantity) - $discountValue;
                $lineSubtotal = max(0, $lineSubtotal);

                $subtotal += ($unitPrice * $quantity);
                $discountTotal += $discountValue;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code ?? $product->sku ?? null,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'discount_value' => $discountValue,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $taxTotal = 0;
            $total = max(0, $subtotal - $discountTotal + $taxTotal);

            $order = $this->orderRepository->create([
                'store_id' => $storeId,
                'customer_id' => $payload['customer_id'] ?? null,
                'warehouse_id' => $payload['warehouse_id'],
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_status' => $payload['payment_status'],
                'delivery_method' => $payload['delivery_method'],
                'due_date' => $payload['due_date'] ?? null,
                'status' => 'menunggu',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $order->items()->createMany($itemsData);

            $this->adjustStockForOrderItems($order, $payload['items'], 'out');

            return $this->orderRepository->findDetail($order->id, $storeId);
        });
    }

    public function deleteOrder(Order $order): void
    {
        if ($order->status === 'selesai') {
            throw new Exception("ORDER_CONFLICT: Order yang sudah selesai tidak dapat dihapus.");
        }

        if ($order->status === 'diproses') {
            throw new Exception("ORDER_CONFLICT: Order sedang diproses, silakan batalkan terlebih dahulu sebelum menghapus.");
        }

        if ($order->items()->exists()) {
            $this->adjustStockForOrderItems($order, $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->all(), 'in');
        }

        $this->orderRepository->delete($order);
    }

    public function updateStatus(Order $order, string $newStatus, int|string $userId): Order
    {
        $allowedTransitions = [
            'menunggu' => ['diproses', 'dibatalkan'],
            'diproses' => ['selesai', 'dibatalkan'],
            'selesai' => [],
            'dibatalkan' => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$order->status] ?? [])) {
            throw new Exception("ORDER_CONFLICT: Transisi status order dari {$order->status} ke {$newStatus} tidak valid.");
        }

        if ($newStatus === 'dibatalkan') {
            $this->adjustStockForOrderItems($order, $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->all(), 'in');
        }

        $order->status = $newStatus;
        $order->updated_by = $userId;
        $order->save();

        return $order;
    }
}