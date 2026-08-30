<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePaymentMethodRequest;
use App\Http\Requests\Settings\UpdatePaymentMethodRequest;
use App\Models\Settings\PaymentMethod;
use App\Services\Settings\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentMethodService $service,
    ) {}

    protected function storeId(Request $request): int
    {
        return (int) $request->user()->getStoreId();
    }

    public function index(Request $request): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat metode pembayaran.',
            ], 403);
        }

        $methods = $this->service->index($this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar metode pembayaran berhasil dimuat.',
            'data' => $methods,
        ]);
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menambah metode pembayaran.',
            ], 403);
        }

        $method = $this->service->store($request->validated(), $this->storeId($request), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil ditambahkan.',
            'data' => $method,
        ], 201);
    }

    public function show(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat metode pembayaran.',
            ], 403);
        }

        $method = $this->service->show($paymentMethod, $this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Detail metode pembayaran berhasil dimuat.',
            'data' => $method,
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk memperbarui metode pembayaran.',
            ], 403);
        }

        $method = $this->service->update($request->validated(), $paymentMethod, $this->storeId($request), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil diperbarui.',
            'data' => $method,
        ]);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus metode pembayaran.',
            ], 403);
        }

        $this->service->destroy($paymentMethod, $this->storeId($request));

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dihapus.',
        ]);
    }

    public function toggle(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if (! in_array($request->user()->role?->name, ['owner', 'manajer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengubah status metode pembayaran.',
            ], 403);
        }

        $method = $this->service->toggle($paymentMethod, $this->storeId($request), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Status metode pembayaran berhasil diperbarui.',
            'data' => $method,
        ]);
    }
}
