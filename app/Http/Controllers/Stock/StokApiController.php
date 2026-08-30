<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\CheckStockRequest;
use App\Services\Stock\StockBalanceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StokApiController extends Controller
{
    public function __construct(
        protected StockBalanceService $stockBalanceService
    ) {}

    public function check(CheckStockRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->profile?->store_id;

        if (! $storeId) {
            throw new HttpException(403, 'User tidak memiliki toko yang terdaftar.');
        }

        try {
            $data = $this->stockBalanceService->checkStock(
                $storeId,
                (int) $request->input('product_id'),
                (int) $request->input('warehouse_id'),
                (int) $request->input('unit_id')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
