<?php

namespace App\Services\Jualan\SalesReturn;

use App\Models\Customer\Customer;
use App\Models\Jualan\Order;
use App\Models\Jualan\OrderItem;
use App\Models\Jualan\SalesReturn;
use App\Models\Jualan\SalesReturnItem;
use App\Repositories\Jualan\SalesReturnRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\Products\Product;
use App\Models\Stock\StockMovement;

class SalesReturnService
{
    public function __construct(
        protected SalesReturnRepository $repository
    ) {}

    public function list(int|string $storeId, array $filters): array
    {
        $paginator = $this->repository->paginate($storeId, $filters);

        $items = \App\Http\Resources\Jualan\SalesReturnResource::collection($paginator->items())->resolve();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function detail(int|string $storeId, SalesReturn $salesReturn): SalesReturn
    {
        $detail = $this->repository->findDetail($storeId, $salesReturn->id);
        if (!$detail) {
            throw new Exception("Retur pembeli tidak ditemukan.", 404);
        }

        return $detail;
    }

    public function create(int|string $storeId, int $userId, array $payload): SalesReturn
    {
        return DB::transaction(function () use ($storeId, $userId, $payload) {
            $order = Order::where('store_id', $storeId)
                ->where('id', $payload['order_id'])
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new Exception("Order tidak ditemukan.", 404);
            }

            if ($order->status !== 'selesai') {
                throw new Exception("Hanya order dengan status selesai yang dapat diretur.", 422);
            }

            $returnableItems = $this->repository->returnableItems($order);
            $returnableMap = collect($returnableItems)->keyBy('id');

            $itemsToInsert = [];
            $totalRefund = 0;

            foreach ($payload['items'] as $item) {
                $orderItemId = $item['order_item_id'];
                $qtyReturned = (float) $item['qty_returned'];

                if (!$returnableMap->has($orderItemId)) {
                    throw new Exception("Item order #{$orderItemId} tidak terdaftar pada order ini.", 422);
                }

                $targetReturnable = $returnableMap->get($orderItemId);

                if ($qtyReturned > $targetReturnable['returnable_qty']) {
                    throw new Exception("Jumlah retur untuk {$targetReturnable['product_name']} melebihi batas returnable qty ({$targetReturnable['returnable_qty']}).", 422);
                }

                $subtotal = $qtyReturned * $targetReturnable['unit_price'];
                $totalRefund += $subtotal;

                $itemsToInsert[] = [
                    'order_item_id' => $orderItemId,
                    'product_id' => $targetReturnable['product_id'],
                    'product_name' => $targetReturnable['product_name'],
                    'product_code' => $targetReturnable['product_code'],
                    'qty_sold' => $targetReturnable['qty_sold'],
                    'qty_returned' => $qtyReturned,
                    'unit_price' => $targetReturnable['unit_price'],
                    'condition' => $item['condition'],
                    'subtotal' => $subtotal,
                ];
            }

            if ($totalRefund <= 0 || empty($itemsToInsert)) {
                throw new Exception("Item retur tidak boleh kosong.", 422);
            }

            $returnNumber = $this->repository->generateReturnNumber($storeId);

            $salesReturn = $this->repository->create([
                'store_id' => $storeId,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'return_number' => $returnNumber,
                'reason' => $payload['reason'],
                'notes' => $payload['notes'] ?? null,
                'refund_method' => $payload['refund_method'],
                'total_refund' => $totalRefund,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($itemsToInsert as $row) {
                $salesReturn->items()->create($row);
            }

            return $this->repository->findDetail($storeId, $salesReturn->id);
        });
    }

    public function updateStatus(int|string $storeId, int $userId, SalesReturn $salesReturn, string $newStatus): array
    {
        if ($salesReturn->store_id != $storeId) {
            throw new Exception("Akses ditolak.", 403);
        }

        if ($salesReturn->status !== 'pending') {
            throw new Exception("Status retur final tidak dapat diubah.", 409);
        }

        DB::transaction(function () use ($salesReturn, $newStatus, $userId) {
            $this->repository->updateStatus($salesReturn, $newStatus, $userId);

            if ($newStatus === 'disetujui') {
                $this->restoreApprovedStock($salesReturn);
            }
        });

        return [
            'message' => 'Status retur pembeli berhasil diperbarui.',
            'status' => $newStatus,
        ];
    }

    public function filterOptions(int|string $storeId): array
    {
        $orders = Order::where('store_id', $storeId)
            ->where('status', 'selesai')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'name' => $o->order_number ?? $o->code ?? '',
                'label' => $o->order_number ?? $o->code ?? '',
                'number' => $o->order_number ?? $o->code ?? '',
                'code' => $o->order_number ?? $o->code ?? '',
            ]);

        $customers = Customer::where('store_id', $storeId)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name,
                'code' => $c->code ?? $c->customer_code ?? null,
            ]);

        return [
            'status_options' => ['semua', 'pending', 'disetujui', 'ditolak', 'dibatalkan'],
            'refund_method_options' => ['semua', 'tunai', 'saldo_toko', 'tukar_barang'],
            'orders' => $orders,
            'customers' => $customers,
        ];
    }

    public function eligibleOrders(int|string $storeId, array $filters): array
    {
        return $this->repository->eligibleOrders($storeId, $filters);
    }

    public function returnableItems(int|string $storeId, Order $order): array
    {
        if ((int) $order->store_id !== (int) $storeId) {
            throw new Exception('Akses ditolak.', 403);
        }

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

            $result[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? $item->product?->name ?? 'Produk',
                'qty_sold' => $qtySold,
                'qty_returned_before' => $qtyReturnedBefore,
                'returnable_qty' => $returnableQty,
                'unit_price' => (float) ($item->unit_price ?? $item->price ?? $item->sell_price ?? 0),
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

    public function print(int|string $storeId, SalesReturn $salesReturn)
    {
        $detail = $this->repository->findDetail($storeId, $salesReturn->id);
        if (!$detail) {
            throw new Exception("Retur tidak ditemukan.", 404);
        }

        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 50 >>\nstream\nBT /F1 12 Tf 100 700 TD (Retur Pembeli: {$detail->return_number}) Tj ET\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="retur-pembeli-' . $detail->return_number . '.pdf"',
        ]);
    }

    private function shouldRestoreStock(?string $condition): bool
    {
        $normalized = strtolower(trim((string) $condition));

        return in_array($normalized, ['baik', 'bagus', 'normal'], true);
    }

    private function restoreApprovedStock(SalesReturn $salesReturn): void
    {
        foreach ($salesReturn->items()->with('product')->get() as $item) {
            if (! $this->shouldRestoreStock($item->condition)) {
                continue;
            }

            $qty = (float) ($item->qty_returned ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $product = Product::where('store_id', $salesReturn->store_id)
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                continue;
            }

            $before = (float) ($product->initial_stock ?? 0);
            $after = $before + $qty;

            $product->update([
                'initial_stock' => $after,
            ]);

            StockMovement::create([
                'store_id' => $salesReturn->store_id,
                'product_id' => $product->id,
                'reference_type' => SalesReturn::class,
                'reference_id' => $salesReturn->id,
                'movement_type' => 'stock_in',
                'quantity' => $qty,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'notes' => 'Retur pembeli disetujui: '.$salesReturn->return_number,
                'movement_at' => now(),
                'created_by' => $salesReturn->approved_by ?? $salesReturn->created_by,
            ]);
        }
    }
}