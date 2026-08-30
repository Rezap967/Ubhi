<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\ResolveBarcodeRequest;
use App\Http\Resources\Kasir\PosProductResource;
use App\Services\Kasir\ProductLookupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BarcodeController extends Controller
{
    public function __construct(
        protected ProductLookupService $productLookupService
    ) {}

    /**
     * GET /api/v1/pos/barcode/{barcode}
     */
    public function resolve(ResolveBarcodeRequest $request, string $barcode): JsonResponse
    {
        try {
            $product = $this->productLookupService->resolveBarcode(
                $request->user(),
                $barcode
            );

            return response()->json([
                'success' => true,
                'message' => 'Produk barcode berhasil ditemukan.',
                'data' => new PosProductResource($product),
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], $e->getStatusCode());
        }
    }
}
