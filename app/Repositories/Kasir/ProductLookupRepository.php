<?php

namespace App\Repositories\Kasir;

use App\Models\Products\Category;
use App\Models\Products\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductLookupRepository
{
    /**
     * Query dasar untuk produk yang siap dijual di POS berdasarkan store.
     */
    public function querySellableProducts(int $storeId): Builder
    {
        return Product::query()
            ->with(['category', 'brand', 'unit'])
            ->where('store_id', $storeId)
            // Status produk di seluruh sistem ini memakai bahasa Indonesia
            // ('aktif' / 'nonaktif'), sesuai standar di modul Manajemen Produk
            // (ProductView.vue: form status, filter status, badge status).
            // Sebelumnya baris ini memakai 'active' (bahasa Inggris) sehingga
            // tidak pernah cocok dengan data produk manapun -> hasil query
            // POS selalu kosong meskipun produk sudah aktif & punya harga.
            ->where('status', 'aktif')
            // ->where('can_be_sold', true) // Jika kolom ini ada
            ->whereNotNull('sell_price')
            ->where('sell_price', '>', 0);
    }

    /**
     * Query pencarian produk POS.
     */
    public function searchProducts(int $storeId, array $filters): Builder
    {
        $query = $this->querySellableProducts($storeId);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('sku', 'like', $search)
                    ->orWhere('barcode', 'like', $search);
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['barcode'])) {
            $query->where('barcode', $filters['barcode']);
        }

        if (! empty($filters['sku'])) {
            $query->where('sku', $filters['sku']);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        if ($sort === 'name') {
            $query->orderBy('name', $direction);
        } elseif ($sort === 'price') {
            $query->orderBy('sell_price', $direction);
        } elseif ($sort === 'best_selling') {
            $period = $filters['period'] ?? 'today';
            $endDate = now()->endOfDay();
            $startDate = match ($period) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => now()->startOfDay(),
            };

            $salesSubquery = DB::table('pos_transaction_items')
                ->join('pos_transactions', 'pos_transactions.id', '=', 'pos_transaction_items.transaction_id')
                ->where('pos_transactions.store_id', $storeId)
                ->where('pos_transactions.status', 'completed')
                ->whereBetween('pos_transactions.transaction_at', [$startDate, $endDate])
                ->select('pos_transaction_items.product_id', DB::raw('SUM(pos_transaction_items.quantity) as sold_quantity'))
                ->groupBy('pos_transaction_items.product_id');

            $query->leftJoinSub($salesSubquery, 'sales', function ($join) {
                $join->on('products.id', '=', 'sales.product_id');
            })
                ->select('products.*', DB::raw('COALESCE(sales.sold_quantity, 0) as total_sold'))
                ->orderByDesc('total_sold')
                ->orderBy('products.name', 'asc');
        }

        return $query;
    }

    /**
     * Cari produk berdasarkan barcode dengan limit 2 (untuk cek duplikasi).
     */
    public function findProductsByBarcode(int $storeId, string $barcode): Collection
    {
        return $this->querySellableProducts($storeId)
            ->where('barcode', $barcode)
            ->limit(2)
            ->get();
    }

    /**
     * Dapatkan daftar kategori beserta jumlah produk yang siap jual.
     */
    public function getCategoryProductCounts(int $storeId): Collection
    {
        return Category::query()
            ->where('status', 'active')
            ->whereHas('products', function (Builder $q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('status', 'aktif')
                    ->whereNotNull('sell_price')
                    ->where('sell_price', '>', 0);
            })
            ->withCount(['products' => function (Builder $q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('status', 'aktif')
                    ->whereNotNull('sell_price')
                    ->where('sell_price', '>', 0);
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * Dapatkan top selling products.
     */
    public function getTopSellingProducts(int $storeId, CarbonInterface $startDate, CarbonInterface $endDate, int $limit): Collection
    {
        // TODO: Implementasi asli membutuhkan tabel pos_transactions dan pos_transaction_items.
        // Karena tabel tersebut belum di-migrate, kita gunakan fallback mengembalikan Collection kosong.

        return collect([]);

        /*
        // QUERY ASLI (Siap pakai ketika tabel sudah ada):

        return \DB::table('pos_transaction_items')
            ->join('pos_transactions', 'pos_transactions.id', '=', 'pos_transaction_items.transaction_id')
            ->join('products', 'products.id', '=', 'pos_transaction_items.product_id')
            ->where('pos_transactions.store_id', $storeId)
            ->where('pos_transactions.status', 'completed')
            ->whereBetween('pos_transactions.transaction_at', [$startDate, $endDate])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.sell_price as price',
                \DB::raw('SUM(pos_transaction_items.quantity) as sold_quantity'),
                \DB::raw('SUM(pos_transaction_items.subtotal) as sales_total')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.sell_price')
            ->orderByDesc('sold_quantity')
            ->limit($limit)
            ->get();
        */
    }
}
