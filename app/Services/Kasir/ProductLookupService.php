<?php

namespace App\Services\Kasir;

use App\Models\Products\Product;
use App\Models\User;
use App\Repositories\Kasir\ProductLookupRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProductLookupService
{
    public function __construct(
        protected ProductLookupRepository $repository
    ) {}

    /**
     * Search produk POS.
     * Stok diambil dari stock_balances (sumber tunggal), bukan dari initial_stock.
     */
    public function searchProducts(User $user, array $filters): LengthAwarePaginator
    {
        $storeId = (int) $user->getStoreId();

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $query = $this->repository->searchProducts($storeId, $filters);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Stok diambil langsung dari products.initial_stock (sumber live POS)
        $paginator->getCollection()->transform(function ($product) {
            $product->stock_available = (float) ($product->initial_stock ?? 0);
            $product->stock_status = $this->calculateStockStatus($product->stock_available);
            $product->can_be_sold = true;

            return $product;
        });

        return $paginator;
    }

    /**
     * Resolve barcode menjadi 1 produk.
     */
    public function resolveBarcode(User $user, string $barcode): Product
    {
        $storeId = (int) $user->getStoreId();

        $products = $this->repository->findProductsByBarcode($storeId, $barcode);

        if ($products->isEmpty()) {
            throw new NotFoundHttpException('Barcode tidak ditemukan.');
        }

        if ($products->count() > 1) {
            throw new ConflictHttpException('Barcode duplikat pada master data.');
        }

        $product = $products->first();

        if ($product->status !== 'aktif') {
            throw new UnprocessableEntityHttpException('Produk tidak aktif atau tidak dapat dijual.');
        }

        if ((float) $product->sell_price <= 0) {
            throw new UnprocessableEntityHttpException('Produk belum memiliki harga jual aktif.');
        }

        $stock = (float) ($product->initial_stock ?? 0);

        $product->stock_available = $stock;
        $product->stock_status = $this->calculateStockStatus($stock);
        $product->can_be_sold = true;

        return $product;
    }

    /**
     * Dapatkan daftar kategori dengan jumlah produk.
     */
    public function getCategories(User $user, bool $includeAll = true): Collection
    {
        $storeId = (int) $user->getStoreId();

        $categories = $this->repository->getCategoryProductCounts($storeId);

        $result = $categories->map(function ($cat) {
            return (object) [
                'id' => $cat->id,
                'name' => $cat->name,
                'product_count' => $cat->products_count,
            ];
        });

        if ($includeAll) {
            $totalProducts = $result->sum('product_count');
            $result->prepend((object) [
                'id' => null,
                'name' => 'Semua',
                'product_count' => $totalProducts,
            ]);
        }

        return $result;
    }

    /**
     * Dapatkan daftar produk terlaris berdasarkan transaksi completed.
     */
    public function getTopSellingProducts(User $user, array $filters): Collection
    {
        $storeId = (int) $user->getStoreId();
        $limit = $filters['limit'] ?? 10;
        $period = $filters['period'] ?? 'today';

        if ($period === 'custom') {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
        } else {
            $endDate = now();
            $startDate = match ($period) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => now()->startOfDay(),
            };
        }

        $topSelling = $this->repository->getTopSellingProducts($storeId, $startDate, $endDate, $limit);

        if ($topSelling->isEmpty()) {
            return $topSelling;
        }

        return $topSelling->map(function ($item) {
            $item->stock_available = 0;
            $item->stock_status = $this->calculateStockStatus($item->stock_available);

            return $item;
        });
    }

    /**
     * Menentukan status stok.
     */
    public function calculateStockStatus(float $stockAvailable): string
    {
        if ($stockAvailable <= 0) {
            return 'out_of_stock';
        }

        if ($stockAvailable <= 5) {
            return 'low_stock';
        }

        return 'available';
    }
}
