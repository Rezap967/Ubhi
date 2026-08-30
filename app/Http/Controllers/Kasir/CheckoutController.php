<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\CheckoutRequest;
use App\Http\Resources\Kasir\PosTransactionResource;
use App\Services\Kasir\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
    ) {}

    /**
     * GET /api/v1/pos/payment-methods
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $this->checkoutService->getPaymentMethods($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dimuat.',
            'data' => $methods,
        ]);
    }

    /**
     * POST /api/v1/pos/checkout
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->checkoutService->checkout($request->user(), $request->validated());
            $transaction = $result['transaction'];
            $isExisting = $result['is_existing'];

            if ($isExisting) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi sudah pernah diproses.',
                    'data' => new PosTransactionResource($transaction),
                ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diselesaikan.',
                'data' => new PosTransactionResource($transaction),
            ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * Parse dan format HttpException menjadi JSON error response.
     */
    private function httpError(HttpException $e): JsonResponse
    {
        $message = $e->getMessage();
        $errors = (object) [];

        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $message;
            unset($decoded['message']);
            $errors = (object) $decoded;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $e->getStatusCode());
    }
}
