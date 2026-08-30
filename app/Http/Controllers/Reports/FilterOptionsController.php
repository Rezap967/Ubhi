<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Kasir\PosPayment;
use App\Models\Products\Brand;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;

class FilterOptionsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $user->getStoreId();

        if (! $storeId) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki toko terdaftar.',
            ], 403);
        }

        // Kasir — dari store aktif
        $cashiers = User::whereHas('profile', fn ($q) => $q->where('store_id', $storeId))
            ->whereHas('role', fn ($q) => $q->where('name', 'kasir'))
            ->select('id', 'name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'label' => $u->name]);

        // Produk — dari store aktif
        $products = Product::where('store_id', $storeId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'label' => $p->name]);

        // Kategori — yang memiliki produk di store aktif (karena category tidak punya store_id)
        $categories = Category::whereHas('products', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'label' => $c->name]);

        $warehouses = Warehouse::query()
            ->where('store_id', $storeId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name, 'label' => $warehouse->name]);

        $brands = Brand::whereHas('products', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($brand) => ['id' => $brand->id, 'name' => $brand->name, 'label' => $brand->name]);

        // Metode pembayaran — dari transaksi di store aktif
        $paymentMethods = PosPayment::whereHas('transaction', fn ($q) => $q->where('store_id', $storeId))
            ->distinct()
            ->pluck('payment_method')
            ->map(fn ($m) => ['id' => $m, 'name' => ucfirst($m), 'code' => $m, 'label' => ucfirst($m)])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Filter options loaded.',
            'data' => [
                'cashiers' => $cashiers,
                'products' => $products,
                'categories' => $categories,
                'warehouses' => $warehouses,
                'brands' => $brands,
                'payment_methods' => $paymentMethods,
            ],
        ]);
    }
}
