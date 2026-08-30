<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\StockBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StockBalanceController extends Controller
{
    public function __construct(
        protected StockBalanceService $stockBalanceService
    ) {}

    /**
     * Menampilkan stok saat ini.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->profile?->store_id;

        if (! $storeId) {
            throw new HttpException(403, 'User tidak memiliki toko yang terdaftar.');
        }

        $filters = [];

        if ($request->filled('product_id')) {
            $filters['product_id'] = $request->integer('product_id');
        }

        if ($request->has('warehouse_id')) {
            $filters['warehouse_id'] = $request->integer('warehouse_id');
        }

        $stockBalances = $this->stockBalanceService->getCurrentStock(
            $storeId,
            $filters
        );

        return response()->json([
            'success' => true,
            'message' => 'Data stok berhasil diambil.',
            'data' => $stockBalances,
        ]);
    }
}
