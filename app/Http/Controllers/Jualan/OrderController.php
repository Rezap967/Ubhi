<?php

namespace App\Http\Controllers\Jualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jualan\Orders\StoreOrderRequest;
use App\Http\Requests\Jualan\Orders\UpdateOrderRequest;
use App\Http\Requests\Jualan\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\Jualan\OrderCollection;
use App\Http\Resources\Jualan\OrderResource;
use App\Models\Customer\Customer;
use App\Models\Jualan\Order;
use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Repositories\Jualan\OrderRepository;
use App\Services\Jualan\Orders\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderService $orderService
    ) {}

    private function success($data = null, string $message = 'Berhasil.', array $meta = [], int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
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
            'errors' => (object) $errors,
            'code' => $code,
        ], $status);
    }

    private function getStoreId(Request $request): int|string
    {
        return $request->user()->store_id ?? $request->header('X-Store-ID', 1);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);

        $customers = Customer::where('store_id', $storeId)
            ->get(['id', 'name', 'code', 'phone'])
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name,
                'code' => $c->code ?? null,
                'phone' => $c->phone ?? null,
            ]);

        // Menggunakan kolom yang benar-benar ada di database: sell_price & initial_stock
        $products = Product::where('store_id', $storeId)
            ->get(['id', 'name', 'sku', 'sell_price', 'initial_stock'])
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => $p->name,
                'code' => $p->sku,
                'sku' => $p->sku,
                'price' => (float) $p->sell_price,
                'sell_price' => (float) $p->sell_price,
                'stock' => (float) ($p->initial_stock ?? 0),
            ]);

        $warehouses = Warehouse::where('store_id', $storeId)
            ->get(['id', 'name', 'code'])
            ->map(fn($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'label' => $w->name,
                'code' => $w->code ?? null,
            ]);

        return $this->success([
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'status_options' => ['semua', 'menunggu', 'diproses', 'selesai', 'dibatalkan'],
            'payment_status_options' => ['semua', 'belum_bayar', 'parsial', 'lunas'],
            'delivery_method_options' => ['semua', 'ambil_ditempat', 'pengiriman'],
        ], 'Filter order pelanggan berhasil dimuat.');
    }
    public function index(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $orders = $this->orderRepository->paginate($request->all(), $storeId);

        return response()->json([
            'success' => true,
            'message' => 'Daftar order pelanggan berhasil dimuat.',
            'data' => new OrderCollection($orders),
            'errors' => null,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $order = $this->orderRepository->findDetail($id, $storeId);

        if (!$order) {
            return $this->error('Order pelanggan tidak ditemukan.', 'ORDER_NOT_FOUND', [], 404);
        }

        return $this->success(new OrderResource($order), 'Detail order pelanggan berhasil dimuat.');
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $storeId = $this->getStoreId($request);
            $userId = $request->user()->id;

            $order = $this->orderService->createOrder($request->validated(), $storeId, $userId);

            return $this->success(new OrderResource($order), 'Order pelanggan berhasil dibuat.', [], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 'ORDER_SERVER_ERROR', [], 500);
        }
    }

    /* public function update(UpdateOrderRequest $request, $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $order = $this->orderRepository->findDetail($id, $storeId);

        if (!$order) {
            return $this->error('Order pelanggan tidak ditemukan.', 'ORDER_NOT_FOUND', [], 404);
        }

        try {
            $userId = $request->user()->id;
            $updatedOrder = $this->orderService->updateOrder($order, $request->validated(), $storeId, $userId);

            return $this->success(new OrderResource($updatedOrder), 'Order pelanggan berhasil diperbarui.');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'ORDER_CONFLICT:')) {
                $cleanMsg = str_replace('ORDER_CONFLICT: ', '', $e->getMessage());
                return $this->error($cleanMsg, 'ORDER_CONFLICT', [], 409);
            }
            return $this->error($e->getMessage(), 'ORDER_SERVER_ERROR', [], 500);
        }
    } */

    public function destroy(Request $request, $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $order = Order::where('store_id', $storeId)->find($id);

        if (!$order) {
            return $this->error('Order pelanggan tidak ditemukan.', 'ORDER_NOT_FOUND', [], 404);
        }

        try {
            $this->orderService->deleteOrder($order);
            return $this->success(null, 'Order pelanggan berhasil dihapus.');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'ORDER_CONFLICT:')) {
                $cleanMsg = str_replace('ORDER_CONFLICT: ', '', $e->getMessage());
                return $this->error($cleanMsg, 'ORDER_CONFLICT', [], 409);
            }
            return $this->error($e->getMessage(), 'ORDER_SERVER_ERROR', [], 500);
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $order = Order::where('store_id', $storeId)->find($id);

        if (!$order) {
            return $this->error('Order pelanggan tidak ditemukan.', 'ORDER_NOT_FOUND', [], 404);
        }

        try {
            $userId = $request->user()->id;
            $updatedOrder = $this->orderService->updateStatus($order, $request->validated()['status'], $userId);

            return $this->success([
                'message' => 'Status order pelanggan berhasil diperbarui.',
                'status' => $updatedOrder->status,
            ], 'Status order pelanggan berhasil diperbarui.');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'ORDER_CONFLICT:')) {
                $cleanMsg = str_replace('ORDER_CONFLICT: ', '', $e->getMessage());
                return $this->error($cleanMsg, 'ORDER_CONFLICT', [], 409);
            }
            return $this->error($e->getMessage(), 'ORDER_SERVER_ERROR', [], 500);
        }
    }

   public function printReceipt(Request $request, $id)
    {
        $storeId = $this->getStoreId($request);
        
        // Eager load relasi untuk menghindari masalah N+1 query
        $order = Order::with(['customer', 'user', 'items.product', 'store'])
            ->where('store_id', $storeId)
            ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        $store = $order->store ?? \App\Models\Store::find($storeId);

        // Opsi: Render HTML langsung jika parameter format=html dipanggil (opsional untuk debugging)
        if ($request->query('format') === 'html') {
            return view('pdf-ubhisir/Jualan/SalesReceipt', compact('order', 'store'));
        }

        // Generate PDF menggunakan Blade template
        $pdf = Pdf::loadView('pdf-ubhisir/Jualan/SalesReceipt', compact('order', 'store'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('struk-order-' . ($order->order_number ?? $order->id) . '.pdf');
    }
    public function printDocument(Request $request, $id)
    {
        $storeId = $this->getStoreId($request);

        // Eager load relasi untuk menghindari N+1 query
        $order = Order::with(['customer', 'user', 'items.product', 'store'])
            ->where('store_id', $storeId)
            ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        $store = $order->store ?? \App\Models\Store::find($storeId);

        // Konversi total transaksi ke format terbilang
        $terbilang = $this->terbilang($order->total_amount ?? 0) . ' rupiah';

        // Opsi render HTML langsung jika dipanggil via query ?format=html
        if ($request->query('format') === 'html') {
            return view('pdf-ubhisir/Jualan/SalesOrderDocument', compact('order', 'store', 'terbilang'));
        }

        // Generate PDF menggunakan Blade view
        $pdf = Pdf::loadView('pdf-ubhisir/Jualan/SalesOrderDocument', compact('order', 'store', 'terbilang'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('dokumen-order-' . ($order->order_number ?? $order->id) . '.pdf');
    }

    /**
     * Helper terbilang nominal angka ke teks Bahasa Indonesia
     */
    private function terbilang($angka)
    {
        $angka = abs($angka);
        $baca = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $terbilang = '';

        if ($angka < 12) {
            $terbilang = ' ' . $baca[$angka];
        } elseif ($angka < 20) {
            $terbilang = $this->terbilang($angka - 10) . ' belas';
        } elseif ($angka < 100) {
            $terbilang = $this->terbilang((int)($angka / 10)) . ' puluh' . $this->terbilang($angka % 10);
        } elseif ($angka < 200) {
            $terbilang = ' seratus' . $this->terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $terbilang = $this->terbilang((int)($angka / 100)) . ' ratus' . $this->terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $terbilang = ' seribu' . $this->terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $terbilang = $this->terbilang((int)($angka / 1000)) . ' ribu' . $this->terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $terbilang = $this->terbilang((int)($angka / 1000000)) . ' juta' . $this->terbilang($angka % 1000000);
        }

        return trim($terbilang);
    }
}