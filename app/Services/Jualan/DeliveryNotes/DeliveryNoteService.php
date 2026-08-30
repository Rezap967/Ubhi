<?php

namespace App\Services\Jualan\DeliveryNotes;

use App\Models\Jualan\DeliveryNote;
use App\Models\Jualan\Order;
use App\Repositories\Jualan\DeliveryNoteRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    public function __construct(
        protected DeliveryNoteRepository $repository
    ) {}

    public function list(int|string $storeId, array $filters): array
    {
        $paginator = $this->repository->paginate($storeId, $filters);

        $items = \App\Http\Resources\Jualan\DeliveryNoteResource::collection($paginator->items())->resolve();

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

    public function detail(int|string $storeId, DeliveryNote $deliveryNote): DeliveryNote
    {
        $detail = $this->repository->findDetail($storeId, $deliveryNote->id);
        if (!$detail) {
            throw new Exception("Surat Jalan tidak ditemukan.", 404);
        }

        return $detail;
    }

    public function create(int|string $storeId, int $userId, array $payload): DeliveryNote
    {
        return DB::transaction(function () use ($storeId, $userId, $payload) {
            $sourceType = $payload['source_type'];
            $sourceOrderId = $sourceType === 'order_pelanggan'
                ? $payload['customer_order_id']
                : $payload['order_id'];

            $order = Order::where('store_id', $storeId)
                ->where('id', $sourceOrderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new Exception("Order sumber tidak ditemukan.", 404);
            }

            if ($sourceType === 'order_pelanggan' && !in_array($order->status, ['diproses', 'selesai'])) {
                throw new Exception("Order pelanggan harus berstatus diproses atau selesai.", 422);
            }

            $eligibleItems = $this->repository->eligibleOrderItems($order, $sourceType);
            $eligibleMap = collect($eligibleItems)->keyBy('id');

            $itemsToInsert = [];
            foreach ($payload['items'] as $item) {
                $orderItemId = $item['order_item_id'];
                $qty = (float) $item['quantity'];

                if (!$eligibleMap->has($orderItemId)) {
                    throw new Exception("Item order #{$orderItemId} tidak terdaftar pada order ini.", 422);
                }

                $target = $eligibleMap->get($orderItemId);

                if ($qty > $target['remaining_quantity']) {
                    throw new Exception("Jumlah pengiriman untuk {$target['product_name']} ({$qty}) melebihi sisa sisa kuantitas ({$target['remaining_quantity']}).", 422);
                }

                $itemsToInsert[] = [
                    'order_item_id' => $orderItemId,
                    'product_id' => $target['product_id'],
                    'product_name' => $target['product_name'],
                    'product_code' => $target['product_code'],
                    'quantity' => $qty,
                ];
            }

            if (empty($itemsToInsert)) {
                throw new Exception("Item Surat Jalan tidak boleh kosong.", 422);
            }

            $deliveryNumber = $this->repository->generateDeliveryNumber($storeId);

            $deliveryNote = $this->repository->create([
                'store_id' => $storeId,
                'delivery_number' => $deliveryNumber,
                'source_type' => $sourceType,
                'order_id' => $sourceType === 'transaksi_penjualan' ? $order->id : null,
                'customer_order_id' => $sourceType === 'order_pelanggan' ? $order->id : null,
                'recipient_name' => $payload['recipient_name'],
                'recipient_address' => $payload['recipient_address'],
                'delivery_date' => $payload['delivery_date'] ?? null,
                'status' => 'draft',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($itemsToInsert as $row) {
                $deliveryNote->items()->create($row);
            }

            return $this->repository->findDetail($storeId, $deliveryNote->id);
        });
    }

    public function update(int|string $storeId, int $userId, DeliveryNote $deliveryNote, array $payload): DeliveryNote
    {
        if ($deliveryNote->store_id != $storeId) {
            throw new Exception("Akses ditolak.", 403);
        }

        if ($deliveryNote->status !== 'draft') {
            throw new Exception("Surat Jalan hanya dapat diedit saat status draft.", 409);
        }

        return DB::transaction(function () use ($storeId, $userId, $deliveryNote, $payload) {
            $sourceType = $payload['source_type'];
            $sourceOrderId = $sourceType === 'order_pelanggan'
                ? $payload['customer_order_id']
                : $payload['order_id'];

            $order = Order::where('store_id', $storeId)
                ->where('id', $sourceOrderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new Exception("Order sumber tidak ditemukan.", 404);
            }

            $eligibleItems = $this->repository->eligibleOrderItems($order, $sourceType, $deliveryNote->id);
            $eligibleMap = collect($eligibleItems)->keyBy('id');

            $itemsToInsert = [];
            foreach ($payload['items'] as $item) {
                $orderItemId = $item['order_item_id'];
                $qty = (float) $item['quantity'];

                if (!$eligibleMap->has($orderItemId)) {
                    throw new Exception("Item order #{$orderItemId} tidak terdaftar pada order ini.", 422);
                }

                $target = $eligibleMap->get($orderItemId);

                if ($qty > $target['remaining_quantity']) {
                    throw new Exception("Jumlah pengiriman untuk {$target['product_name']} ({$qty}) melebihi sisa kuantitas ({$target['remaining_quantity']}).", 422);
                }

                $itemsToInsert[] = [
                    'order_item_id' => $orderItemId,
                    'product_id' => $target['product_id'],
                    'product_name' => $target['product_name'],
                    'product_code' => $target['product_code'],
                    'quantity' => $qty,
                ];
            }

            $this->repository->update($deliveryNote, [
                'source_type' => $sourceType,
                'order_id' => $sourceType === 'transaksi_penjualan' ? $order->id : null,
                'customer_order_id' => $sourceType === 'order_pelanggan' ? $order->id : null,
                'recipient_name' => $payload['recipient_name'],
                'recipient_address' => $payload['recipient_address'],
                'delivery_date' => $payload['delivery_date'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $userId,
            ]);

            $deliveryNote->items()->delete();

            foreach ($itemsToInsert as $row) {
                $deliveryNote->items()->create($row);
            }

            return $this->repository->findDetail($storeId, $deliveryNote->id);
        });
    }

    public function delete(int|string $storeId, DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->store_id != $storeId) {
            throw new Exception("Akses ditolak.", 403);
        }

        if (!in_array($deliveryNote->status, ['draft', 'dibatalkan'])) {
            throw new Exception("Surat Jalan yang sudah dikirim tidak dapat dihapus.", 409);
        }

        $this->repository->delete($deliveryNote);
    }

    public function updateStatus(int|string $storeId, int $userId, DeliveryNote $deliveryNote, string $newStatus): array
    {
        if ($deliveryNote->store_id != $storeId) {
            throw new Exception("Akses ditolak.", 403);
        }

        $allowedTransitions = [
            'draft' => ['dikirim', 'dibatalkan'],
            'dikirim' => ['terkirim', 'dibatalkan'],
            'terkirim' => [],
            'dibatalkan' => [],
        ];

        $currentStatus = $deliveryNote->status;

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            throw new Exception("Transisi status Surat Jalan tidak valid.", 409);
        }

        $this->repository->updateStatus($deliveryNote, $newStatus);

        return [
            'message' => 'Status Surat Jalan berhasil diperbarui.',
            'status' => $newStatus,
        ];
    }

    public function filterOptions(int|string $storeId): array
    {
        $customerOrders = Order::where('store_id', $storeId)
            ->whereIn('status', ['diproses', 'selesai'])
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

        return [
            'status_options' => ['semua', 'draft', 'dikirim', 'terkirim', 'dibatalkan'],
            'source_type_options' => ['semua', 'transaksi_penjualan', 'order_pelanggan'],
            'orders' => [],
            'customer_orders' => $customerOrders,
        ];
    }

    public function eligibleOrders(int|string $storeId, array $filters): array
    {
        return $this->repository->eligibleOrders($storeId, $filters);
    }

    public function eligibleOrderItems(int|string $storeId, Order $order, array $filters = []): array
    {
        if ($order->store_id != $storeId) {
            throw new Exception("Order tidak ditemukan.", 404);
        }

        $sourceType = $filters['source_type'] ?? 'order_pelanggan';
        return $this->repository->eligibleOrderItems($order, $sourceType);
    }

    public function print(int|string $storeId, DeliveryNote $deliveryNote)
    {
        $detail = $this->repository->findDetail($storeId, $deliveryNote->id);
        if (!$detail) {
            throw new Exception("Surat Jalan tidak ditemukan.", 404);
        }

        $detail->update(['printed_at' => now()]);

        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 50 >>\nstream\nBT /F1 12 Tf 100 700 TD (Surat Jalan: {$detail->delivery_number}) Tj ET\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="surat-jalan-' . $detail->delivery_number . '.pdf"',
        ]);
    }
}