<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Controller;
use App\Models\Konsinyasi\ConsignmentItem;
use App\Services\Konsinyasi\ConsignmentItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsignmentItemController extends Controller
{
    public function __construct(
        protected ConsignmentItemService $itemService,
    ) {
    }

    protected function getStoreId(Request $request): ?int
    {
        $user = $request->user();

        if ($user && $user->profile?->store_id) {
            return (int) $user->profile->store_id;
        }

        return null;
    }

    protected function unassignedStoreResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Store aktif tidak ditemukan pada profil user.',
            'data' => null,
        ], 422);
    }

    /**
     * GET /api/v1/consignments/items
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = (int) $request->input('page', 1);

        $sortByWhitelist = [
            'created_at' => 'consignment_items.created_at',
            'product_name' => 'products.name',
            'supplier_name' => 'suppliers.name',
            'selling_price' => 'consignment_items.selling_price',
            'consignment_price' => 'consignment_items.consignment_price',
            'status' => 'consignment_items.status',
        ];

        $sortByInput = $request->input('sort_by', 'created_at');
        $sortBy = $sortByWhitelist[$sortByInput] ?? 'consignment_items.created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Eager load stockBalances agar jumlah kuantitas terhitung dari tabel relasi
        $query = ConsignmentItem::query()
            ->select('consignment_items.*')
            ->where('consignment_items.store_id', $storeId)
            ->with([
                'product:id,name,sku', 
                'supplier:id,name', 
                'warehouse:id,name',
                'stockBalances'
            ]);

        // Filter: Supplier
        if ($supplierId = $request->input('supplier_id')) {
            $query->where('consignment_items.supplier_id', $supplierId);
        }

        // Filter: Warehouse (Map ke default_warehouse_id)
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('consignment_items.default_warehouse_id', $warehouseId);
        }

        // Filter: Status
        $status = $request->input('status');
        if ($status && in_array($status, ['active', 'inactive', 'closed'], true)) {
            $query->where('consignment_items.status', $status);
        }

        // Filter: Date Range
        if ($dateStart = $request->input('date_start')) {
            $query->whereDate('consignment_items.created_at', '>=', $dateStart);
        }
        if ($dateEnd = $request->input('date_end')) {
            $query->whereDate('consignment_items.created_at', '<=', $dateEnd);
        }

        // Filter: Search Keyword
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                       ->orWhere('sku', 'like', "%{$search}%");
                })->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Sorting
        if (in_array($sortByInput, ['product_name', 'supplier_name'], true)) {
            if ($sortByInput === 'product_name') {
                $query->leftJoin('products', 'products.id', '=', 'consignment_items.product_id')
                      ->orderBy('products.name', $sortDir);
            } elseif ($sortByInput === 'supplier_name') {
                $query->leftJoin('suppliers', 'suppliers.id', '=', 'consignment_items.supplier_id')
                      ->orderBy('suppliers.name', $sortDir);
            }
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedItems = collect($paginator->items())->map(function ($item) {
            // Hitung agregat kuantitas dari relasi stockBalances jika ada
            $qtyAvailable = $item->relationLoaded('stockBalances') 
                ? (float) $item->stockBalances->sum('quantity_available') 
                : (float) ($item->quantity_available ?? 0);

            $qtySold = $item->relationLoaded('stockBalances') 
                ? (float) $item->stockBalances->sum('quantity_sold') 
                : (float) ($item->quantity_sold ?? 0);

            $qtyReturned = $item->relationLoaded('stockBalances') 
                ? (float) $item->stockBalances->sum('quantity_returned') 
                : (float) ($item->quantity_returned ?? 0);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'supplier_id' => $item->supplier_id,
                'warehouse_id' => $item->default_warehouse_id, // Map DB default_warehouse_id -> contract frontend warehouse_id
                'product_name' => $item->product?->name ?? '',
                'product_sku' => $item->product?->sku ?? '',
                'supplier_name' => $item->supplier?->name ?? '',
                'warehouse_name' => $item->warehouse?->name ?? '',
                'selling_price' => (float) $item->selling_price,
                'consignment_price' => (float) $item->consignment_price,
                'commission_type' => $item->commission_type ?? 'margin',
                'commission_value' => (float) ($item->commission_value ?? 0),
                'quantity_available' => $qtyAvailable,
                'quantity_sold' => $qtySold,
                'quantity_returned' => $qtyReturned,
                'status' => $item->status,
                'received_date' => $item->received_date ? $item->received_date->format('Y-m-d') : null,
                'reference_no' => $item->reference_no ?? null,
                'notes' => $item->notes,
                'created_at' => $item->created_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data barang konsinyasi berhasil diambil.',
            'data' => [
                'items' => $formattedItems,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/consignments/items
     */
    public function store(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'selling_price' => 'required|numeric|min:0',
            'consignment_price' => 'required|numeric|min:0|lte:selling_price',
            'commission_type' => 'required|in:margin,percentage,fixed',
            'commission_value' => 'required|numeric|min:0',
            'initial_quantity' => 'nullable|numeric|min:1',
            'received_date' => 'nullable|date',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ], [
            'consignment_price.lte' => 'Harga konsinyasi tidak boleh lebih besar dari harga jual.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek duplikasi menggunakan kolom DB default_warehouse_id
        $exists = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->where('product_id', $request->product_id)
            ->where('supplier_id', $request->supplier_id)
            ->where('default_warehouse_id', $request->warehouse_id)
            ->where('status', '!=', 'closed')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi dengan produk, supplier, dan gudang tersebut sudah terdaftar.',
                'data' => null,
            ], 409);
        }

        $validatedData = $validator->validated();

        // Remap input warehouse_id dari frontend ke default_warehouse_id untuk Service / Model DB
        $validatedData['default_warehouse_id'] = $validatedData['warehouse_id'];

        $item = $this->itemService->createConsignmentItem(
            data: $validatedData,
            storeId: $storeId,
            userId: $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Barang konsinyasi berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * GET /api/v1/consignments/items/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $item = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->with([
                'product:id,name,sku', 
                'supplier:id,name', 
                'warehouse:id,name',
                'stockBalances'
            ])
            ->find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $qtyAvailable = $item->relationLoaded('stockBalances') 
            ? (float) $item->stockBalances->sum('quantity_available') 
            : (float) ($item->quantity_available ?? 0);

        $qtySold = $item->relationLoaded('stockBalances') 
            ? (float) $item->stockBalances->sum('quantity_sold') 
            : (float) ($item->quantity_sold ?? 0);

        $qtyReturned = $item->relationLoaded('stockBalances') 
            ? (float) $item->stockBalances->sum('quantity_returned') 
            : (float) ($item->quantity_returned ?? 0);

        $data = [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'supplier_id' => $item->supplier_id,
            'warehouse_id' => $item->default_warehouse_id, // Map default_warehouse_id -> warehouse_id
            'product_name' => $item->product?->name ?? '',
            'product_sku' => $item->product?->sku ?? '',
            'supplier_name' => $item->supplier?->name ?? '',
            'warehouse_name' => $item->warehouse?->name ?? '',
            'selling_price' => (float) $item->selling_price,
            'consignment_price' => (float) $item->consignment_price,
            'commission_type' => $item->commission_type ?? 'margin',
            'commission_value' => (float) ($item->commission_value ?? 0),
            'quantity_available' => $qtyAvailable,
            'quantity_sold' => $qtySold,
            'quantity_returned' => $qtyReturned,
            'status' => $item->status,
            'received_date' => $item->received_date ? $item->received_date->format('Y-m-d') : null,
            'reference_no' => $item->reference_no ?? null,
            'notes' => $item->notes,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'stock_ledger' => [],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail barang konsinyasi berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    /**
     * PATCH /api/v1/consignments/items/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $item = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'selling_price' => 'nullable|numeric|min:0',
            'consignment_price' => 'nullable|numeric|min:0',
            'commission_type' => 'nullable|in:margin,percentage,fixed',
            'commission_value' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,closed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
                'errors' => $validator->errors(),
            ], 422);
        }

        $sellingPrice = $request->input('selling_price', $item->selling_price);
        $consignmentPrice = $request->input('consignment_price', $item->consignment_price);

        if ($sellingPrice !== null && $consignmentPrice !== null && $sellingPrice < $consignmentPrice) {
            return response()->json([
                'success' => false,
                'message' => 'Harga jual tidak boleh lebih kecil dari harga konsinyasi.',
                'data' => null,
                'errors' => [
                    'selling_price' => ['Harga jual tidak boleh lebih kecil dari harga konsinyasi.'],
                ],
            ], 422);
        }

        $this->itemService->updateConsignmentItem($item, $validator->validated(), $storeId);

        return response()->json([
            'success' => true,
            'message' => 'Aturan barang konsinyasi berhasil diperbarui.',
            'data' => $item->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/v1/consignments/items/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $item = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        // Ambil total quantity_sold dan quantity_returned dari relasi stockBalances
        $qtySold = (float) $item->stockBalances()->sum('quantity_sold');
        $qtyReturned = (float) $item->stockBalances()->sum('quantity_returned');

        if ($qtySold > 0 || $qtyReturned > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi sudah memiliki transaksi dan tidak dapat dihapus.',
                'data' => null,
            ], 409);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Barang konsinyasi berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    /**
     * PATCH /api/v1/consignments/items/{id}/close
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        $item = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Barang konsinyasi tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        if ($item->status === 'closed') {
            return response()->json([
                'success' => true,
                'message' => 'Barang konsinyasi sudah berstatus closed.',
                'data' => $item,
            ], 200);
        }

        $item->status = 'closed';
        if ($request->filled('reason')) {
            $item->notes = trim(($item->notes ? $item->notes . "\n" : '') . 'Alasan ditutup: ' . $request->input('reason'));
        }
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Barang konsinyasi berhasil ditutup.',
            'data' => $item,
        ], 200);
    }

    /**
     * GET /api/v1/consignments/items/export
     */
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        $storeId = $this->getStoreId($request);
        if (! $storeId) {
            return $this->unassignedStoreResponse();
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Konsinyasi\ConsignmentItemsExport($storeId),
            'konsinyasi-barang-' . now()->format('Ymd-His') . '.xlsx'
        );
    } 
}