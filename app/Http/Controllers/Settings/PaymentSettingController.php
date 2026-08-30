<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReorderPaymentRequest;
use App\Http\Requests\Settings\StorePaymentRequest;
use App\Http\Requests\Settings\UpdatePaymentRequest;
use App\Services\Settings\PaymentSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function __construct(
        protected PaymentSettingService $service
    ) {}

    protected function storeId(Request $request): int
    {
        return $request->user()->profile->store_id;
    }

    public function index(Request $request): JsonResponse
    {
        $payments = $this->service->index($this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar metode pembayaran berhasil dimuat.',
            'data' => $payments,
        ], 200);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->service->store($request->validated(), $this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil ditambahkan.',
            'data' => $payment,
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $payment = $this->service->show($id, $this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Detail metode pembayaran berhasil dimuat.',
            'data' => $payment,
        ], 200);
    }

    public function update(UpdatePaymentRequest $request, int $id): JsonResponse
    {
        $payment = $this->service->show($id, $this->storeId($request));
        $payment = $this->service->update($request->validated(), $payment);

        return response()->json([
            'success' => true,
            'message' => 'Data metode pembayaran berhasil diperbarui.',
            'data' => $payment,
        ], 200);
    }

    public function toggleStatus(int $id, Request $request): JsonResponse
    {
        $payment = $this->service->show($id, $this->storeId($request));
        $payment = $this->service->toggleStatus($payment);

        return response()->json([
            'success' => true,
            'message' => "Status metode pembayaran diubah menjadi {$payment->status}.",
            'data' => $payment,
        ], 200);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $payment = $this->service->show($id, $this->storeId($request));
        $this->service->destroy($payment);

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dihapus.',
        ], 200);
    }

    public function reorder(ReorderPaymentRequest $request): JsonResponse
    {
        $this->service->reorder($request->validated('payments'), $this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Urutan metode pembayaran berhasil diperbarui.',
        ], 200);
    }
}
