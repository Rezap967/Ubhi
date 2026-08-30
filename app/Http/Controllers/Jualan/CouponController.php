<?php

namespace App\Http\Controllers\Jualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jualan\Coupons\GenerateCouponsRequest;
use App\Http\Requests\Jualan\Coupons\StoreCouponRequest;
use App\Http\Requests\Jualan\Coupons\UpdateCouponRequest;
use App\Models\Jualan\Coupon;
use App\Repositories\Jualan\CouponRepository;
use App\Services\Jualan\Coupons\CouponGeneratorService;
use App\Services\Jualan\Coupons\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
        protected CouponGeneratorService $generatorService,
        protected CouponRepository $repository,
    ) {}

    /**
     * GET /api/v1/coupons
     * List coupons with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:aktif,nonaktif,berakhir'],
            'discount_type' => ['nullable', 'string', 'in:persentase,nominal'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $storeId = (int) $request->user()->getStoreId();
        $coupons = $this->repository->paginate($filters, $storeId);

        return response()->json([
            'success' => true,
            'message' => 'Daftar kupon berhasil dimuat.',
            'data' => $coupons->items(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'last_page' => $coupons->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/coupons/create
     * Return lookup options for coupon creation form.
     */
    public function create(Request $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        return response()->json([
            'success' => true,
            'message' => 'Opsi kupon berhasil dimuat.',
            'data' => [
                'discount_types' => ['persentase', 'nominal'],
                'statuses' => ['aktif', 'nonaktif', 'berakhir'],
            ],
        ]);
    }

    /**
     * POST /api/v1/coupons
     * Store a new coupon.
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $coupon = $this->couponService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil dibuat.',
            'data' => $coupon,
        ], 201);
    }

    /**
     * GET /api/v1/coupons/{coupon}
     * Show coupon details.
     */
    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('view', $coupon);

        // Verify store scope
        if ($coupon->store_id !== (int) $request->user()->getStoreId()) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail kupon berhasil dimuat.',
            'data' => $coupon,
        ]);
    }

    /**
     * PUT /api/v1/coupons/{coupon}
     * Update coupon.
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        // Verify store scope
        if ($coupon->store_id !== (int) $request->user()->getStoreId()) {
            abort(403);
        }

        $updated = $this->couponService->update($coupon, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * DELETE /api/v1/coupons/{coupon}
     * Soft delete coupon.
     */
    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);

        // Verify store scope
        if ($coupon->store_id !== (int) $request->user()->getStoreId()) {
            abort(403);
        }

        $this->couponService->delete($coupon);

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil dihapus.',
        ]);
    }

    /**
     * POST /api/v1/coupons/generate
     * Generate bulk coupons.
     */
    public function generate(GenerateCouponsRequest $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $storeId = (int) $request->user()->getStoreId();
        $result = $this->generatorService->generate($request->validated(), $storeId, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => "Berhasil membuat {$result['count']} kupon.",
            'data' => [
                'generated_count' => $result['count'],
                'codes' => $result['codes'],
            ],
        ], 201);
    }
}
