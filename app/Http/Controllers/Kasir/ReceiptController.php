<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kasir\PosReceiptResource;
use App\Models\Kasir\PosTransaction;
use App\Services\Kasir\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReceiptController extends Controller
{
    public function __construct(
        protected ReceiptService $receiptService
    ) {}

    /**
     * GET /api/v1/pos/transactions/{transaction}/receipt
     */
    public function show(Request $request, PosTransaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request->user(), $transaction);

        $receipt = $this->receiptService->getReceipt($transaction);

        return response()->json([
            'success' => true,
            'message' => 'Receipt berhasil dimuat.',
            'data' => new PosReceiptResource($receipt),
        ]);
    }

    /**
     * POST /api/v1/pos/transactions/{transaction}/print
     */
    public function print(Request $request, PosTransaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request->user(), $transaction);

        $receipt = $this->receiptService->printReceipt($transaction);

        return response()->json([
            'success' => true,
            'message' => 'Receipt berhasil dicetak.',
            'data' => new PosReceiptResource($receipt),
        ]);
    }

    /**
     * Authorize that the user can access this transaction.
     */
    private function authorizeTransaction($user, PosTransaction $transaction): void
    {
        $userStoreId = $user->getStoreId();

        if (! $userStoreId || (int) $transaction->store_id !== (int) $userStoreId) {
            throw new AccessDeniedHttpException('Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
