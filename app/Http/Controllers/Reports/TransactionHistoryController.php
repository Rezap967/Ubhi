<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TransactionHistoryRequest;
use App\Http\Resources\Reports\TransactionDetailResource;
use App\Http\Resources\Reports\TransactionHistoryResource;
use App\Policies\Reports\TransactionHistoryPolicy;
use App\Services\Reports\TransactionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransactionHistoryController extends Controller
{
    public function __construct(protected TransactionHistoryService $service) {}

    public function index(TransactionHistoryRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! (new TransactionHistoryPolicy)->viewAny($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke riwayat transaksi.',
            ], 403);
        }

        try {
            $filters = $request->filters();
            $paginator = $this->service->getTransactions($user, $filters);

            return response()->json([
                'success' => true,
                'message' => 'Riwayat transaksi berhasil dimuat.',
                'data' => TransactionHistoryResource::collection($paginator->items()),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function filterOptions(): JsonResponse
    {
        $user = Auth::user();

        if (! (new TransactionHistoryPolicy)->viewAny($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
            ], 403);
        }

        try {
            $options = $this->service->getFilterOptions($user);

            return response()->json([
                'success' => true,
                'message' => 'Filter options berhasil dimuat.',
                'data' => $options,
            ]);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $transaction = $this->service->getTransactionDetail($user, $id);

            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan.',
                ], 404);
            }

            if (! (new TransactionHistoryPolicy)->view($user, $transaction)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke transaksi ini.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail transaksi berhasil dimuat.',
                'data' => new TransactionDetailResource($transaction),
            ]);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
