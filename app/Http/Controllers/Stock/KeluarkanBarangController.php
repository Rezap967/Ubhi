<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockOutRequest;
use App\Services\Stock\StockOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KeluarkanBarangController extends Controller
{
    public function __construct(protected StockOutService $stockOutService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->profile?->store_id;

        if (! $storeId) {
            throw new HttpException(403, 'User tidak memiliki toko.');
        }

        $stockOuts = $this->stockOutService->list($storeId);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengeluaran barang berhasil dimuat.',
            'data' => $stockOuts,
        ]);
    }

    public function store(StoreStockOutRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->profile?->store_id;

        if (! $storeId) {
            throw new HttpException(403, 'User tidak memiliki toko.');
        }

        try {
            $stockOut = $this->stockOutService->store(
                $storeId,
                $user->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran barang berhasil disimpan.',
                'data' => $stockOut,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
