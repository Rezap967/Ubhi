<?php

namespace App\Http\Controllers\Belanja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Belanja\PurchaseOrders\ReceivePurchaseOrderRequest;
use App\Http\Requests\Belanja\PurchaseOrders\StorePurchaseOrderRequest;
use App\Http\Requests\Belanja\PurchaseOrders\UpdatePurchaseOrderRequest;
use App\Http\Resources\Belanja\PurchaseOrderCollection;
use App\Http\Resources\Belanja\PurchaseOrderResource;
use App\Models\Belanja\PurchaseOrder;
use App\Models\Products\Product;
use App\Models\Products\Supplier;
use App\Repositories\Belanja\PurchaseOrderRepository;
use App\Services\Belanja\PurchaseOrders\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderRepository $purchaseOrderRepository,
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * GET /purchase-orders
     */
    public function index(Request $request): PurchaseOrderCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $filters = $request->only(['search', 'status', 'sort_by', 'sort_dir', 'per_page']);
        $paginated = $this->purchaseOrderRepository->paginate((int) $storeId, $filters);

        return new PurchaseOrderCollection($paginated);
    }

    /**
     * POST /purchase-orders
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $po = $this->purchaseOrderService->create(
            (int) $storeId,
            $request->user()->id,
            $request->validated()
        );

        return (new PurchaseOrderResource($po))
            ->additional([
                'success' => true,
                'message' => 'Purchase Order berhasil dibuat.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /purchase-orders/{purchaseOrder}
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * PUT /purchase-orders/{purchaseOrder}
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $po = $this->purchaseOrderService->update(
            $purchaseOrder,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order berhasil diperbarui.',
            'data' => new PurchaseOrderResource($po),
        ]);
    }

    /**
     * DELETE /purchase-orders/{purchaseOrder}
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $this->purchaseOrderService->delete($purchaseOrder, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order berhasil dihapus.',
        ]);
    }

    /**
     * PATCH /purchase-orders/{purchaseOrder}/submit
     */
    public function submit(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $po = $this->purchaseOrderService->submit($purchaseOrder);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order berhasil dikirim ke supplier.',
            'status' => $po->status,
        ]);
    }

    /**
     * PATCH /purchase-orders/{purchaseOrder}/cancel
     */
    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $po = $this->purchaseOrderService->cancel($purchaseOrder);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order berhasil dibatalkan.',
            'status' => $po->status,
        ]);
    }

    /**
     * PATCH /purchase-orders/{purchaseOrder}/receive
     */
    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('receive', $purchaseOrder);

        $po = $this->purchaseOrderService->receiveItems(
            $purchaseOrder,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan barang berhasil dicatat.',
            'status' => $po->status,
            'items' => $po->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'qty_ordered' => (int) $item->qty_ordered,
                    'qty_received' => (int) $item->qty_received,
                    'qty_pending' => (int) $item->qty_pending,
                ];
            }),
        ]);
    }

    /**
     * GET /purchase-orders/filter-options
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $storeId = $request->user()->profile?->store_id;
        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil user tidak memiliki store_id.');
        }

        $suppliers = Supplier::where('status', 'aktif')->get(['id', 'name']);
        $products = Product::where('store_id', (int) $storeId)->where('status', 'aktif')->get(['id', 'name', 'sku']);

        return response()->json([
            'success' => true,
            'data' => [
                'suppliers' => $suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
                'products' => $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]),
            ],
        ]);
    }
}
