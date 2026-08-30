<?php

namespace App\Http\Controllers\Jualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jualan\DeliveryNotes\StoreDeliveryNoteRequest;
use App\Http\Requests\Jualan\DeliveryNotes\UpdateDeliveryNoteRequest;
use App\Http\Requests\Jualan\DeliveryNotes\UpdateDeliveryNoteStatusRequest;
use App\Http\Resources\Jualan\DeliveryNoteResource;
use App\Models\Jualan\DeliveryNote;
use App\Models\Jualan\Order;
use App\Services\Jualan\DeliveryNotes\DeliveryNoteService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function __construct(
        protected DeliveryNoteService $service
    ) {}

    protected function getStoreId(Request $request): int|string
    {
        return $request->user()->store_id ?? 1;
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
            return $this->success($data, 'Filter Surat Jalan berhasil dimuat.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_SERVER_ERROR', [], 500);
        }
    }

    public function eligibleOrders(Request $request): JsonResponse
    {
        try {
            $data = $this->service->eligibleOrders($this->getStoreId($request), $request->all());
            return $this->success($data, 'Order yang bisa dibuatkan Surat Jalan berhasil dimuat.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_SERVER_ERROR', [], 500);
        }
    }

    public function eligibleOrderItems(Request $request, Order $order): JsonResponse
    {
        try {
            $data = $this->service->eligibleOrderItems($this->getStoreId($request), $order, $request->all());
            return $this->success($data, 'Item order yang bisa dikirim berhasil dimuat.');
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_VALIDATION_ERROR', [], $status);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->service->list($this->getStoreId($request), $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Daftar Surat Jalan berhasil dimuat.',
                'data' => $data,
                'errors' => null,
                'code' => null,
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_SERVER_ERROR', [], 500);
        }
    }

    public function show(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        try {
            $detail = $this->service->detail($this->getStoreId($request), $deliveryNote);
            return $this->success(new DeliveryNoteResource($detail), 'Detail Surat Jalan berhasil dimuat.');
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_NOT_FOUND', [], $status);
        }
    }

    public function store(StoreDeliveryNoteRequest $request): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $userId = $request->user()->id;

            $deliveryNote = $this->service->create($storeId, $userId, $request->validated());

            return $this->success(new DeliveryNoteResource($deliveryNote), 'Surat Jalan berhasil dibuat.', [], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), 'DELIVERY_NOTE_VALIDATION_ERROR', [], $status);
        }
    }

    public function update(UpdateDeliveryNoteRequest $request, DeliveryNote $deliveryNote): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $userId = $request->user()->id;

            $updated = $this->service->update($storeId, $userId, $deliveryNote, $request->validated());

            return $this->success(new DeliveryNoteResource($updated), 'Surat Jalan berhasil diperbarui.');
        } catch (Exception $e) {
            $code = $e->getCode() === 409 ? 'DELIVERY_NOTE_CONFLICT' : 'DELIVERY_NOTE_VALIDATION_ERROR';
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), $code, [], $status);
        }
    }

    public function destroy(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $this->service->delete($storeId, $deliveryNote);

            return $this->success(null, 'Surat Jalan berhasil dihapus.');
        } catch (Exception $e) {
            $code = $e->getCode() === 409 ? 'DELIVERY_NOTE_CONFLICT' : 'DELIVERY_NOTE_VALIDATION_ERROR';
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), $code, [], $status);
        }
    }

    public function updateStatus(UpdateDeliveryNoteStatusRequest $request, DeliveryNote $deliveryNote): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $userId = $request->user()->id;

            $res = $this->service->updateStatus($storeId, $userId, $deliveryNote, $request->validated()['status']);

            return $this->success($res, 'Status Surat Jalan berhasil diperbarui.');
        } catch (Exception $e) {
            $code = $e->getCode() === 409 ? 'DELIVERY_NOTE_CONFLICT' : 'DELIVERY_NOTE_VALIDATION_ERROR';
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error($e->getMessage(), $code, [], $status);
        }
    }

    public function print(Request $request, DeliveryNote $deliveryNote)
    {
        try {
            return $this->service->print($this->getStoreId($request), $deliveryNote);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}