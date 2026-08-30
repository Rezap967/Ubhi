<?php

namespace App\Http\Controllers\Belanja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Belanja\PurchaseReturns\StorePurchaseReturnRequest;
use App\Http\Requests\Belanja\PurchaseReturns\UpdatePurchaseReturnRequest;
use App\Http\Resources\Belanja\PurchaseReturnCollection;
use App\Http\Resources\Belanja\PurchaseReturnResource;
use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseReturn;
use App\Models\Products\Supplier;
use App\Repositories\Belanja\PurchaseReturnRepository;
use App\Services\Belanja\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected PurchaseReturnRepository $purchaseReturnRepository,
        protected PurchaseReturnService $purchaseReturnService
    ) {}

    /**
     * GET /purchase-returns
     */
    public function index(Request $request): PurchaseReturnCollection
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $filters = $request->only(['search', 'status', 'supplier_id', 'purchase_order_id', 'sort_by', 'sort_dir', 'per_page', 'limit']);
        $paginated = $this->purchaseReturnRepository->paginate((int) $storeId, $filters);

        return new PurchaseReturnCollection($paginated);
    }

    /**
     * POST /purchase-returns
     */
    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseReturn::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $pr = $this->purchaseReturnService->create(
            (int) $storeId,
            $request->user()->id,
            $request->validated()
        );

        return (new PurchaseReturnResource($pr))
            ->additional([
                'success' => true,
                'message' => 'Retur berhasil dibuat.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /purchase-returns/{purchaseReturn}
     */
    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('view', $purchaseReturn);

        $purchaseReturn->load(['supplier', 'items.product', 'items.purchaseOrderItem', 'purchaseOrder']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseReturnResource($purchaseReturn),
        ]);
    }

    /**
     * PUT /purchase-returns/{purchaseReturn}
     */
    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('update', $purchaseReturn);

        $pr = $this->purchaseReturnService->update(
            $purchaseReturn,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Retur berhasil diperbarui.',
            'data' => new PurchaseReturnResource($pr),
        ]);
    }

    /**
     * DELETE /purchase-returns/{purchaseReturn}
     */
    public function destroy(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('delete', $purchaseReturn);

        $this->purchaseReturnService->delete($purchaseReturn, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Retur berhasil dihapus.',
        ]);
    }

    /**
     * PATCH /purchase-returns/{purchaseReturn}/submit
     */
    public function submit(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('submit', $purchaseReturn);

        $pr = $this->purchaseReturnService->submit($purchaseReturn);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan retur berhasil dikirim ke supplier.',
            'status' => $pr->status,
        ]);
    }

    /**
     * PATCH /purchase-returns/{purchaseReturn}/confirm
     */
    public function confirm(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('confirm', $purchaseReturn);

        $pr = $this->purchaseReturnService->confirm($purchaseReturn, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Retur berhasil dikonfirmasi oleh supplier.',
            'status' => $pr->status,
        ]);
    }

    /**
     * PATCH /purchase-returns/{purchaseReturn}/cancel
     */
    public function cancel(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('cancel', $purchaseReturn);

        $pr = $this->purchaseReturnService->cancel($purchaseReturn);

        return response()->json([
            'success' => true,
            'message' => 'Retur berhasil dibatalkan.',
            'status' => $pr->status,
        ]);
    }

    /**
     * GET /purchase-returns/filter-options
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $suppliers = Supplier::where('status', 'aktif')->get(['id', 'name']);
        $pos = PurchaseOrder::where('store_id', (int) $storeId)->get(['id', 'po_number']);

        return response()->json([
            'success' => true,
            'data' => [
                'suppliers' => $suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
                'purchase_orders' => $pos->map(fn ($p) => ['id' => $p->id, 'name' => $p->po_number]),
            ],
        ]);
    }

    /**
     * GET /purchase-returns/eligible-purchase-orders
     */
    public function eligiblePurchaseOrders(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $query = PurchaseOrder::where('store_id', (int) $storeId)
            ->whereIn('status', ['Dikirim', 'Sebagian', 'Diterima'])
            ->with(['supplier:id,name', 'items.product:id,name,sku']);

        if ($request->filled('search')) {
            $search = '%'.mb_strtolower($request->search).'%';
            $query->whereRaw('LOWER(po_number) LIKE ?', [$search]);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $pos = $query->get();

        return response()->json([
            'success' => true,
            'data' => $pos->map(function ($po) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => $po->status,
                    'supplier' => $po->supplier ? [
                        'id' => $po->supplier->id,
                        'name' => $po->supplier->name,
                    ] : null,
                    'total' => (float) $po->total,
                    'total_formatted' => $po->total_formatted,
                    'items' => $po->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product' => $item->product ? [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'sku' => $item->product->sku,
                            ] : null,
                            'qty_ordered' => (int) $item->qty_ordered,
                            'qty_received' => (int) $item->qty_received,
                            'max_returnable_qty' => (int) $item->qty_ordered,
                            'unit_price' => (float) $item->unit_price,
                        ];
                    }),
                ];
            }),
        ]);
    }
}
