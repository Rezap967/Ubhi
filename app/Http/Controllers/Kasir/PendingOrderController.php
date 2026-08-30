<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StorePendingOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendingOrderController extends Controller
{
    /**
     * GET /api/v1/pos/pending-orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $storeId = (int) ($request->user()->getStoreId() ?? 0);

            if ($storeId <= 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil mengambil data pesanan tersimpan.',
                    'data' => [],
                ]);
            }

            $orders = DB::table('pending_orders')
                ->where('store_id', $storeId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(static function ($order) {
                    // Decode JSON items string to array
                    if (is_string($order->items)) {
                        $order->items = json_decode($order->items, true) ?? [];
                    }

                    return $order;
                });

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengambil data pesanan tersimpan.',
                'data' => $orders,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pesanan tersimpan: '.$e->getMessage(),
                'data' => null,
                'errors' => null,
            ], 500);
        }
    }

    /**
     * POST /api/v1/pos/pending-orders
     */
    public function store(StorePendingOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user();
            $storeId = (int) ($user?->getStoreId() ?? 0);

            if ($storeId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak memiliki akses ke store. Silakan login kembali.',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }

            $order = DB::transaction(function () use ($validated, $user, $storeId) {
                $items = array_map(static fn ($item) => [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'name' => $item['name'] ?? null,
                    'price' => $item['price'] ?? null,
                    'subtotal' => $item['subtotal'] ?? null,
                ], $validated['items']);

                $id = DB::table('pending_orders')->insertGetId([
                    'store_id' => $storeId,
                    'user_id' => $user?->id,
                    'name' => trim($validated['name']),
                    'customer_name' => $validated['customer_name'] !== null ? trim((string) $validated['customer_name']) : null,
                    'note' => $validated['note'] !== null ? trim((string) $validated['note']) : null,
                    'items' => json_encode($items),
                    'subtotal' => (float) $validated['subtotal'],
                    'tax' => (float) $validated['tax'],
                    'total' => (float) $validated['total'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return DB::table('pending_orders')->where('id', $id)->first();
            });

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil disimpan.',
                'data' => $order,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pesanan: '.$e->getMessage(),
                'data' => null,
                'errors' => null,
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/pos/pending-orders/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $storeId = (int) ($request->user()->getStoreId() ?? 0);

            if ($storeId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak memiliki akses ke store. Silakan login kembali.',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }
            $order = DB::table('pending_orders')
                ->where('id', $id)
                ->where('store_id', $storeId)
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tersimpan tidak ditemukan.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            DB::table('pending_orders')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan tersimpan berhasil dihapus.',
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pesanan tersimpan: '.$e->getMessage(),
                'data' => null,
                'errors' => null,
            ], 500);
        }
    }
}
