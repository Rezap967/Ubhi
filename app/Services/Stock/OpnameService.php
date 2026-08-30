<?php

namespace App\Services\Stock;

use App\Models\Products\Warehouse;
use App\Models\Stock\StockOpnameItem;
use App\Models\Stock\StockOpnameSession;
use App\Repositories\Stock\OpnameRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class OpnameService
{
    public function __construct(
        protected OpnameRepositoryInterface $repository
    ) {}

    // ─── List ─────────────────────────────────────────────────────────────────

    /**
     * Ambil daftar sesi opname dengan pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getList(int $storeId, array $filters): array
    {
        $paginated = $this->repository->paginateSessions($storeId, $filters);

        $items = collect($paginated->items())->map(fn (StockOpnameSession $s) => $this->formatSession($s))->values();

        return [
            'items' => $items,
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ];
    }

    // ─── Create ────────────────────────────────────────────────────────────────

    /**
     * Buat sesi opname baru + snapshot stok items dalam satu transaksi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createSession(int $storeId, int $userId, array $data): array
    {
        return DB::transaction(function () use ($storeId, $userId, $data) {
            $sessionCode = $this->generateSessionCode($storeId);

            $warehouseId = null;
            if ($data['scope_type'] === 'per_warehouse') {
                $warehouseId = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;
            } elseif ($data['scope_type'] === 'all_products') {
                $warehouseId = ! empty($data['warehouse_id'])
                    ? (int) $data['warehouse_id']
                    : $this->resolveDefaultWarehouseId($storeId, $userId);
            } elseif ($data['scope_type'] === 'per_category') {
                $warehouseId = ! empty($data['warehouse_id'])
                    ? (int) $data['warehouse_id']
                    : $this->resolveDefaultWarehouseId($storeId, $userId);
            }

            $session = $this->repository->createSession([
                'store_id' => $storeId,
                'session_code' => $sessionCode,
                'session_name' => $data['session_name'],
                'scope_type' => $data['scope_type'],
                'warehouse_id' => $warehouseId,
                'category_id' => $data['category_id'] ?? null,
                'status' => 'berjalan',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Ambil produk sesuai scope
            $products = $this->repository->getProductsForScope($storeId, [
                'scope_type' => $data['scope_type'],
                'warehouse_id' => $warehouseId,
                'category_id' => $data['category_id'] ?? null,
            ]);

            $productIds = $products->pluck('id')->toArray();

            // Snapshot stok sistem berdasarkan store_id, products, dan warehouse_id
            $stockMap = $this->repository->getStockSnapshot($storeId, $productIds, $warehouseId);

            // Siapkan items untuk bulk insert
            $items = [];

            foreach ($products as $product) {
                $key = $product->id.':'.($warehouseId ?? 'null');
                $systemStock = $stockMap[$key] ?? 0.0;

                $items[] = [
                    'session_id' => $session->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'system_stock' => $systemStock,
                    'physical_stock' => null,
                    'difference' => null,
                    'updated_by' => null,
                ];
            }

            if (! empty($items)) {
                $this->repository->bulkCreateItems($items);
            }

            return [
                'id' => (string) $session->id,
                'session_code' => $session->session_code,
                'session_name' => $session->session_name,
                'status' => $session->status,
                'total_items' => count($items),
            ];
        });
    }

    /**
     * Cari atau buat gudang default toko ('Stok Di Toko' / store_stock).
     */
    public function resolveDefaultWarehouseId(int $storeId, ?int $userId = null): ?int
    {
        $warehouse = Warehouse::query()
            ->where('store_id', $storeId)
            ->where('warehouse_type', 'store_stock')
            ->first() ?? Warehouse::query()
            ->where('store_id', $storeId)
            ->first();

        if ($warehouse) {
            return (int) $warehouse->id;
        }

        // Jika toko belum memiliki warehouse, buat warehouse default
        $created = Warehouse::query()->create([
            'store_id' => $storeId,
            'name' => 'Stok Di Toko',
            'code' => 'TOKO-01',
            'warehouse_type' => 'store_stock',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $created->id;
    }

    // ─── Detail ────────────────────────────────────────────────────────────────

    /**
     * Ambil detail sesi + list items.
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $storeId, int|string $sessionId): array
    {
        $session = $this->repository->findSessionById($storeId, $sessionId);

        if (! $session) {
            abort(404, 'Sesi opname tidak ditemukan.');
        }

        $session->loadCount([
            'items as total_items',
            'items as checked_items' => fn ($q) => $q->whereNotNull('physical_stock'),
        ]);

        $session->load(['creator:id,name', 'approver:id,name', 'warehouse:id,name', 'category:id,name']);

        $items = $session->items()
            ->with(['product:id,name,sku,unit_id', 'product.unit:id,name', 'warehouse:id,name', 'updater:id,name'])
            ->get()
            ->map(fn ($item) => $this->formatItem($item))
            ->values();

        $totalItems = (int) $session->total_items;
        $checkedItems = (int) $session->checked_items;

        return [
            'session' => [
                'id' => (string) $session->id,
                'session_code' => $session->session_code,
                'session_name' => $session->session_name,
                'scope_type' => $session->scope_type,
                'scope_label' => $session->scope_label,
                'warehouse' => $session->warehouse ? [
                    'id' => (string) $session->warehouse->id,
                    'name' => $session->warehouse->name,
                ] : null,
                'category' => $session->category ? [
                    'id' => (string) $session->category->id,
                    'name' => $session->category->name,
                ] : null,
                'status' => $session->status,
                'checked_items' => $checkedItems,
                'total_items' => $totalItems,
                'progress_label' => "{$checkedItems}/{$totalItems}",
                'created_by' => $session->creator ? [
                    'id' => (string) $session->creator->id,
                    'name' => $session->creator->name,
                ] : null,
                'created_at' => $session->created_at?->toIso8601String(),
                'approved_by' => $session->approver ? [
                    'id' => (string) $session->approver->id,
                    'name' => $session->approver->name,
                ] : null,
                'approved_at' => $session->approved_at?->toIso8601String(),
                'notes' => $session->notes,
            ],
            'items' => $items,
        ];
    }

    // ─── Update Item ───────────────────────────────────────────────────────────

    /**
     * Update stok fisik satu item.
     *
     * @param  array{physical_stock: float}  $data
     * @return array<string, mixed>
     */
    public function updateItem(int $storeId, int $userId, int|string $sessionId, int|string $itemId, array $data): array
    {
        $session = $this->repository->findSessionById($storeId, $sessionId);

        if (! $session) {
            abort(404, 'Sesi opname tidak ditemukan.');
        }

        if ($session->isApproved()) {
            $this->abortConflict('Sesi opname sudah disetujui dan tidak dapat diubah.');
        }

        $item = $this->repository->findItemInSession($sessionId, $itemId);

        if (! $item) {
            abort(404, 'Item opname tidak ditemukan.');
        }

        $physicalStock = (float) $data['physical_stock'];
        $difference = $physicalStock - (float) $item->system_stock;

        $updatedItem = $this->repository->updateItemPhysicalStock($item, [
            'physical_stock' => $physicalStock,
            'difference' => $difference,
            'updated_by' => $userId,
        ]);

        $progress = $this->repository->countProgress($sessionId);

        return [
            'item_id' => (string) $updatedItem->id,
            'product_name' => $updatedItem->product?->name ?? '-',
            'system_stock' => (float) $updatedItem->system_stock,
            'physical_stock' => (float) $updatedItem->physical_stock,
            'difference' => (float) $updatedItem->difference,
            'checked_items' => $progress['checked_items'],
            'total_items' => $progress['total_items'],
            'progress_label' => $progress['progress_label'],
        ];
    }

    // ─── Approve ───────────────────────────────────────────────────────────────

    /**
     * Approve sesi dan adjust stock_balances.
     *
     * @return array<string, mixed>
     */
    public function approve(int $storeId, int $userId, int|string $sessionId): array
    {
        return DB::transaction(function () use ($storeId, $userId, $sessionId) {
            $session = $this->repository->findSessionForUpdate($storeId, $sessionId);

            if (! $session) {
                abort(404, 'Sesi opname tidak ditemukan.');
            }

            if ($session->isApproved()) {
                $this->abortConflict('Sesi opname sudah disetujui dan tidak dapat diapprove ulang.');
            }

            $items = $this->repository->getItemsForApproval($session->id);
            $now = now();

            foreach ($items as $item) {
                if ($item->physical_stock === null) {
                    continue;
                }

                $warehouseId = $item->warehouse_id;
                $physicalStock = (float) $item->physical_stock;

                // Lock dan ambil balance existing
                $balance = $this->repository->lockStockBalance($storeId, $item->product_id, $warehouseId);

                if (! $balance) {
                    $balance = $this->repository->createStockBalanceIfMissing(
                        $storeId,
                        $item->product_id,
                        $warehouseId
                    );
                }

                $quantityBefore = (float) $balance->quantity;
                $quantityChange = $physicalStock - $quantityBefore;

                // Skip metadata jika tidak ada perubahan
                if ($quantityChange == 0) {
                    continue;
                }

                $movementType = $quantityChange > 0
                    ? 'opname_adjustment_plus'
                    : 'opname_adjustment_minus';

                $this->repository->updateStockBalance($balance, $physicalStock, [
                    'last_movement_type' => $movementType,
                    'last_reference_no' => $session->session_code,
                    'last_quantity_change' => $quantityChange,
                    'last_changed_by' => $userId,
                    'last_changed_at' => $now,
                ]);
            }

            $approvedSession = $this->repository->approveSession($session, [
                'status' => 'disetujui',
                'approved_by' => $userId,
                'approved_at' => $now,
            ]);

            return [
                'id' => (string) $approvedSession->id,
                'session_code' => $approvedSession->session_code,
                'status' => $approvedSession->status,
                'approved_at' => $approvedSession->approved_at?->toIso8601String(),
            ];
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generate session code: OPN-YYYYMMDD-XXX (counter per store per hari).
     */
    public function generateSessionCode(int $storeId): string
    {
        $today = now()->format('Ymd');
        $count = $this->repository->countSessionsToday($storeId, now()->toDateString());
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "OPN-{$today}-{$sequence}";
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSession(StockOpnameSession $session): array
    {
        $totalItems = (int) ($session->total_items ?? 0);
        $checkedItems = (int) ($session->checked_items ?? 0);

        return [
            'id' => (string) $session->id,
            'session_code' => $session->session_code,
            'session_name' => $session->session_name,
            'scope_type' => $session->scope_type,
            'scope_label' => $session->scope_label,
            'warehouse' => $session->warehouse ? [
                'id' => (string) $session->warehouse->id,
                'name' => $session->warehouse->name,
            ] : null,
            'category' => $session->category ? [
                'id' => (string) $session->category->id,
                'name' => $session->category->name,
            ] : null,
            'status' => $session->status,
            'checked_items' => $checkedItems,
            'total_items' => $totalItems,
            'progress_label' => "{$checkedItems}/{$totalItems}",
            'created_by' => $session->creator ? [
                'id' => (string) $session->creator->id,
                'name' => $session->creator->name,
            ] : null,
            'created_at' => $session->created_at?->toIso8601String(),
            'approved_by' => $session->approver ? [
                'id' => (string) $session->approver->id,
                'name' => $session->approver->name,
            ] : null,
            'approved_at' => $session->approved_at?->toIso8601String(),
            'notes' => $session->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatItem(StockOpnameItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'product_id' => (string) $item->product_id,
            'sku' => $item->product?->sku ?? '-',
            'product_name' => $item->product?->name ?? '-',
            'warehouse_id' => $item->warehouse_id ? (string) $item->warehouse_id : null,
            'warehouse_name' => $item->warehouse?->name ?? null,
            'unit_name' => $item->product?->unit?->name ?? null,
            'system_stock' => (float) $item->system_stock,
            'physical_stock' => $item->physical_stock !== null ? (float) $item->physical_stock : null,
            'difference' => $item->difference !== null ? (float) $item->difference : null,
            'updated_by' => $item->updater ? [
                'id' => (string) $item->updater->id,
                'name' => $item->updater->name,
            ] : null,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Lempar HTTP 409 Conflict sebagai response JSON.
     */
    private function abortConflict(string $message): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) [],
            ], 409)
        );
    }
}
