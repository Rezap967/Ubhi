<?php

namespace App\Http\Controllers\Jualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jualan\SalesReturn\StoreSalesReturnRequest;
use App\Http\Requests\Jualan\SalesReturn\UpdateSalesReturnStatusRequest;
use App\Http\Resources\Jualan\SalesReturnResource;
use App\Models\Jualan\Order;
use App\Models\Jualan\SalesReturn;
use App\Services\Jualan\SalesReturn\SalesReturnService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $service
    ) {}

    protected function getStoreId(Request $request): int|string
    {
        $user = $request->user();

    if ($user->role->name === 'owner' || $user->role->name === 'manajer') {
        $store = \App\Models\Store::query()->where('owner_id', $user->id)->first();

        if ($store) {
            return $store->id;
        }
    }

    return $user->profile?->store_id ?? throw new \RuntimeException('Store tidak ditemukan untuk pengguna ini.');
    }

    private function success($data = null, string $message = 'Berhasil.', array $meta = [], int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'code' => null,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    private function error(string $message, string $code, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => empty($errors) ? (object) [] : $errors,
            'code' => $code,
        ], $status);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        try {
            $data = $this->service->filterOptions($this->getStoreId($request));
            return $this->success($data, 'Filter retur pembeli berhasil dimuat.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'SALES_RETURN_SERVER_ERROR', [], 500);
        }
    }

    public function eligibleOrders(Request $request): JsonResponse
    {
        try {
            $data = $this->service->eligibleOrders($this->getStoreId($request), $request->all());
            return $this->success($data, 'Order yang bisa diretur berhasil dimuat.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'SALES_RETURN_SERVER_ERROR', [], 500);
        }
    }

    public function returnableItems(Request $request, Order $order): JsonResponse
    {
        try {
            $data = $this->service->returnableItems($this->getStoreId($request), $order);
            return $this->success($data, 'Item order yang bisa diretur berhasil dimuat.');
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), 'SALES_RETURN_VALIDATION_ERROR', [], $status);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->service->list($this->getStoreId($request), $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Daftar retur pembeli berhasil dimuat.',
                'data' => $data,
                'errors' => null,
                'code' => null,
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'SALES_RETURN_SERVER_ERROR', [], 500);
        }
    }

    public function show(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $detail = $this->service->detail($this->getStoreId($request), $salesReturn);
            return $this->success(new SalesReturnResource($detail), 'Detail retur pembeli berhasil dimuat.');
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return $this->error($e->getMessage(), 'SALES_RETURN_NOT_FOUND', [], $status);
        }
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $userId = $request->user()->id;

            $salesReturn = $this->service->create($storeId, $userId, $request->validated());

            return $this->success(new SalesReturnResource($salesReturn), 'Retur pembeli berhasil dibuat.', [], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), 'SALES_RETURN_VALIDATION_ERROR', [], $status);
        }
    }

    public function updateStatus(UpdateSalesReturnStatusRequest $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $user = $request->user();
            if (!in_array($user->role->name ?? '', ['owner', 'manajer'])) {
                return $this->error('Anda tidak memiliki izin untuk memproses retur pembeli.', 'SALES_RETURN_FORBIDDEN', [], 403);
            }

            $storeId = $this->getStoreId($request);
            $res = $this->service->updateStatus($storeId, $user->id, $salesReturn, $request->validated()['status']);

            return $this->success($res, 'Status retur pembeli berhasil diperbarui.');
        } catch (Exception $e) {
            $code = $e->getCode() === 409 ? 'SALES_RETURN_CONFLICT' : 'SALES_RETURN_VALIDATION_ERROR';
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), $code, [], $status);
        }
    }

    public function print(Request $request, SalesReturn $salesReturn)
    {
        try {
            return $this->service->print($this->getStoreId($request), $salesReturn);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}