<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Controller;
use App\Models\Konsinyasi\ConsignmentSettlement;
use App\Services\Konsinyasi\ConsignmentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConsignmentSettlementController extends Controller
{
    public function __construct(
        protected ConsignmentSettlementService $settlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->getStoreId();

        $query = ConsignmentSettlement::with(['supplier', 'creator', 'approver'])
            ->where('store_id', $storeId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('settlement_code', 'LIKE', "%{$search}%")
                  ->orWhere('payment_reference_no', 'LIKE', "%{$search}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('name', 'LIKE', "%{$search}%"));
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('period_start', [$request->date_start, $request->date_end]);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_dir', $request->get('sort_order', 'desc'));
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int)$request->get('per_page', $request->get('limit', 20));
        $paginated = $query->paginate($perPage);

        $summaryQuery = ConsignmentSettlement::where('store_id', $storeId);
        $summary = [
            'total_settlements' => (int)$summaryQuery->count(),
            'draft_count' => (int)(clone $summaryQuery)->where('status', 'draft')->count(),
            'approved_count' => (int)(clone $summaryQuery)->where('status', 'approved')->count(),
            'cancelled_count' => (int)(clone $summaryQuery)->where('status', 'cancelled')->count(),
            'total_gross_sales' => (float)(clone $summaryQuery)->where('status', 'approved')->sum('gross_sales'),
            'total_supplier_payable' => (float)(clone $summaryQuery)->where('status', 'approved')->sum('supplier_payable'),
            'total_store_margin' => (float)(clone $summaryQuery)->where('status', 'approved')->sum('store_margin'),
        ];

        $items = collect($paginated->items())->map(fn($item) => $this->formatSettlementListItem($item));

        return response()->json([
            'success' => true,
            'message' => 'Daftar settlement berhasil dimuat.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
                'summary' => $summary,
            ]
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
        ]);

        $storeId = $request->user()->getStoreId();
        $result = $this->settlementService->calculate(
            $storeId,
            (int)$request->supplier_id,
            $request->date_start,
            $request->date_end
        );

        return response()->json([
            'success' => true,
            'message' => 'Kalkulasi settlement berhasil.',
            'data' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'allocation_ids' => 'required|array|min:1',
            'payment_method' => 'nullable|string|in:cash,transfer,other',
            'payment_reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'request_uuid' => 'nullable|uuid',
        ]);

        $storeId = $request->user()->getStoreId();
        $userId = $request->user()->id;

        $settlement = $this->settlementService->createDraft($storeId, $userId, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Settlement berhasil dibuat sebagai DRAFT.',
            'data' => $this->formatSettlementListItem($settlement->load(['supplier', 'creator'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $request->user()->getStoreId();
        $settlement = ConsignmentSettlement::with([
            'supplier', 'creator', 'approver',
            'items.allocation.transaction', 'items.allocation.consignmentItem.product'
        ])->where('store_id', $storeId)->findOrFail($id);

        $formatted = $this->formatSettlementListItem($settlement);
        $formatted['items'] = $settlement->items->map(fn($item) => [
            'sales_allocation_id' => $item->sales_allocation_id,
            'sale_id' => $item->allocation?->pos_transaction_id,
            'sale_no' => $item->allocation?->transaction?->transaction_number ?? '-',
            'sale_date' => $item->allocation?->created_at?->format('Y-m-d H:i:s'),
            'consignment_item_id' => $item->allocation?->consignment_item_id,
            'product_name' => $item->allocation?->consignmentItem?->product?->name ?? 'Produk',
            'product_sku' => $item->allocation?->consignmentItem?->product?->sku ?? '-',
            'quantity_sold' => (float)$item->quantity_sold,
            'selling_price' => (float)($item->gross_amount / max($item->quantity_sold, 1)),
            'gross_amount' => (float)$item->gross_amount,
            'supplier_amount' => (float)$item->supplier_amount,
            'store_margin' => (float)$item->store_margin,
            'settlement_status' => $item->allocation?->settlement_status ?? 'settled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail settlement berhasil dimuat.',
            'data' => $formatted,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $storeId = $request->user()->getStoreId();
        $userId = $request->user()->id;

        $settlement = $this->settlementService->approve($storeId, $userId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Settlement berhasil disetujui (Approved).',
            'data' => $this->formatSettlementListItem($settlement->load(['supplier', 'creator', 'approver'])),
        ]);
    }

    protected function formatSettlementListItem(ConsignmentSettlement $item): array
    {
        return [
            'id' => $item->id,
            'settlement_code' => $item->settlement_code,
            'supplier_id' => $item->supplier_id,
            'supplier' => [
                'id' => $item->supplier?->id,
                'name' => $item->supplier?->name ?? '-',
                'code' => $item->supplier?->code ?? null,
            ],
            'period_start' => $item->period_start->format('Y-m-d'),
            'period_end' => $item->period_end->format('Y-m-d'),
            'gross_sales' => (float)$item->gross_sales,
            'supplier_payable' => (float)$item->supplier_payable,
            'store_margin' => (float)$item->store_margin,
            'payment_method' => $item->payment_method,
            'payment_reference_no' => $item->payment_reference_no,
            'status' => $item->status,
            'approved_by' => $item->approved_by,
            'approved_by_name' => $item->approver?->name,
            'approved_at' => $item->approved_at?->format('Y-m-d H:i:s'),
            'notes' => $item->notes,
            'created_by' => $item->created_by,
            'created_by_name' => $item->creator?->name,
            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
            'journal_entry_id' => $item->journal_entry_id,
        ];
    }
}