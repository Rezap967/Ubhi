<?php

namespace App\Http\Controllers\Jualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jualan\Promotions\StorePromotionRequest;
use App\Http\Requests\Jualan\Promotions\UpdatePromotionRequest;
use App\Models\Jualan\Promotion;
use App\Repositories\Jualan\PromotionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionRepository $promotionRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $storeId = (int) $request->user()->getStoreId();
        $promotions = $this->promotionRepository->paginate($filters, $storeId);

        return response()->json([
            'success' => true,
            'message' => 'Daftar promosi berhasil dimuat.',
            'data' => $promotions->items(),
            'meta' => [
                'current_page' => $promotions->currentPage(),
                'per_page' => $promotions->perPage(),
                'total' => $promotions->total(),
                'last_page' => $promotions->lastPage(),
            ],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        return response()->json([
            'success' => true,
            'message' => 'Lookup promosi berhasil dimuat.',
            'data' => [
                'types' => ['diskon_persen', 'potongan_tetap'],
                'scopes' => ['semua_produk', 'per_produk', 'per_kategori', 'per_brand', 'per_grup_pelanggan'],
                'statuses' => ['draft', 'aktif', 'nonaktif', 'berakhir', 'diarsipkan'],
            ],
        ]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        $validated = $request->validated();
        $targets = $validated['targets'] ?? [];
        $storeId = (int) $request->user()->getStoreId();

        $promotion = $this->promotionRepository->create([
            'store_id' => $storeId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'discount_value' => $validated['discount_value'],
            'max_discount' => $validated['max_discount'] ?? null,
            'min_purchase' => $validated['min_purchase'] ?? null,
            'min_qty' => $validated['min_qty'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'scope' => $validated['scope'],
            'max_usage' => $validated['max_usage'] ?? null,
            'per_customer_limit' => $validated['per_customer_limit'] ?? null,
            'usage_count' => 0,
            'is_stackable' => (bool) ($validated['is_stackable'] ?? false),
            'priority' => (int) ($validated['priority'] ?? 0),
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ], $targets);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil ditambahkan.',
            'data' => $promotion,
        ], 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $this->authorize('view', $promotion);

        return response()->json([
            'success' => true,
            'message' => 'Detail promosi berhasil dimuat.',
            'data' => $promotion->load('targets'),
        ]);
    }

    public function edit(Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        return response()->json([
            'success' => true,
            'message' => 'Data promosi untuk edit berhasil dimuat.',
            'data' => $promotion->load('targets'),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        $validated = $request->validated();
        $targets = $validated['targets'] ?? [];

        if ($promotion->store_id !== (int) $request->user()->getStoreId()) {
            abort(403, 'Promosi tidak milik toko Anda.');
        }

        $promotion = $this->promotionRepository->update($promotion, [
            ...$validated,
            'updated_by' => $request->user()->id,
        ], $targets);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil diperbarui.',
            'data' => $promotion,
        ]);
    }

    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);

        if ($promotion->store_id !== (int) $request->user()->getStoreId()) {
            abort(403, 'Promosi tidak milik toko Anda.');
        }

        $this->promotionRepository->archive($promotion);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil diarsipkan.',
        ]);
    }
}
