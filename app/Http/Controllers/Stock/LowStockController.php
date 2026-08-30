<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\LowStockFilterRequest;
use App\Services\Stock\StockBalanceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LowStockController extends Controller
{
    public function __construct(
        protected StockBalanceService $stockBalanceService
    ) {}

    public function index(LowStockFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->profile?->store_id;

        if (! $storeId) {
            throw new HttpException(403, 'User tidak memiliki toko yang terdaftar.');
        }

        $filters = $request->only(['warehouse_id', 'category_id', 'brand_id']);

        $lowStockData = $this->stockBalanceService->getLowStock($storeId, $filters);

        return response()->json([
            'success' => true,
            'data' => $lowStockData,
        ]);
    }
}
