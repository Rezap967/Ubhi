<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StockReportRequest;
use App\Models\Products\Brand;
use App\Models\Products\Category;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReportController extends Controller
{
    public function index(StockReportRequest $request): JsonResponse
    {
        $storeId = $this->storeId($request);
        $filters = $request->validated();
        $query = $this->baseQuery($storeId, $filters);
        $limit = (int) ($filters['limit'] ?? 10);
        $sort = $filters['sort_by'] ?? 'product_name';
        $direction = $filters['sort_order'] ?? 'asc';
        $sortColumn = match ($sort) {
            'product_name' => DB::raw('(select name from products where products.id = stock_balances.product_id)'),
            'stock_value' => DB::raw('(stock_balances.quantity * (select buy_price from products where products.id = stock_balances.product_id))'),
            'last_movement_at' => 'stock_balances.last_changed_at',
            default => 'stock_balances.'.$sort,
        };
        $rows = $query->orderBy($sortColumn, $direction)->paginate($limit)->withQueryString();
        $summaryQuery = $this->baseQuery($storeId, $filters);
        $summaryRows = $summaryQuery->get();

        $items = $rows->getCollection()->map(fn (StockBalance $balance): array => $this->item($balance))->values();
        $summary = $this->summary($summaryRows);

        return response()->json(['success' => true, 'message' => 'Laporan stok berhasil dimuat.', 'data' => ['summary' => $summary, 'items' => $items, 'pagination' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'last_page' => $rows->lastPage()]]]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $storeId = $this->storeId($request);

        return response()->json(['success' => true, 'message' => 'Filter laporan stok berhasil dimuat.', 'data' => ['warehouses' => Warehouse::query()->where('store_id', $storeId)->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'label' => $row->name]), 'categories' => Category::query()->whereHas('products', fn ($query) => $query->where('store_id', $storeId))->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'label' => $row->name]), 'brands' => Brand::query()->whereHas('products', fn ($query) => $query->where('store_id', $storeId))->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'label' => $row->name])]]);
    }

    public function export(StockReportRequest $request): StreamedResponse
    {
        $storeId = $this->storeId($request);
        $rows = $this->baseQuery($storeId, $request->validated())->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Produk', 'SKU', 'Gudang', 'Kategori', 'Qty', 'Nilai Stok', 'Status']);
            foreach ($rows as $balance) {
                $item = $this->item($balance);
                fputcsv($handle, [$item['product_name'], $item['sku'], $item['warehouse_name'], $item['category_name'], $item['quantity'], $item['stock_value'], $item['is_low_stock'] ? 'Rendah' : 'Aman']);
            }
            fclose($handle);
        }, 'laporan-stok.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function baseQuery(int $storeId, array $filters): Builder
    {
        return StockBalance::query()->where('store_id', $storeId)->with(['product.category', 'product.brand', 'warehouse'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $id)))
            ->when($filters['brand_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('product', fn (Builder $product) => $product->where('brand_id', $id)))
            ->when($filters['low_stock_only'] ?? false, fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->whereNotNull('minimum_stock')->where('minimum_stock', '>', 0)->whereColumn('stock_balances.quantity', '<=', 'products.minimum_stock')))
            ->when($filters['dead_stock_days'] ?? null, fn (Builder $query, $days) => $query->where(function (Builder $nested) use ($days) {
                $nested->whereNull('last_changed_at')->orWhere('last_changed_at', '<=', now()->subDays($days));
            }));
    }

    protected function item(StockBalance $balance): array
    {
        $quantity = (float) $balance->quantity;
        $cost = (float) ($balance->product?->buy_price ?? 0);
        $minimum = $balance->product?->minimum_stock;

        return ['product_id' => $balance->product_id, 'product_name' => $balance->product?->name ?? '-', 'sku' => $balance->product?->sku, 'warehouse_name' => $balance->warehouse?->name ?? 'Stok Umum', 'category_name' => $balance->product?->category?->name, 'brand_name' => $balance->product?->brand?->name, 'quantity' => $quantity, 'minimum_stock' => $minimum !== null ? (float) $minimum : null, 'stock_value' => round($quantity * $cost, 2), 'average_cost' => $cost, 'latest_purchase_price' => $cost, 'last_movement_at' => $balance->last_changed_at?->toIso8601String(), 'is_low_stock' => $minimum !== null && $quantity <= (float) $minimum, 'is_dead_stock' => ! $balance->last_changed_at || $balance->last_changed_at->lte(now()->subDays(30))];
    }

    protected function summary($rows): array
    {
        $items = $rows->map(fn (StockBalance $row) => $this->item($row));
        $products = $items->pluck('product_id')->unique()->count();
        $low = $items->where('is_low_stock', true)->pluck('product_id')->unique()->count();
        $dead = $items->where('is_dead_stock', true)->pluck('product_id')->unique()->count();

        return ['total_stock_value' => round((float) $items->sum('stock_value'), 2), 'total_products' => $products, 'total_sku' => $items->pluck('sku')->filter()->unique()->count(), 'low_stock_count' => $low, 'dead_stock_count' => $dead, 'total_quantity' => (float) $items->sum('quantity')];
    }

    protected function storeId(Request $request): int
    {
        $storeId = $request->user()?->getStoreId();
        abort_unless($storeId, 403, 'User tidak memiliki toko aktif.');

        return (int) $storeId;
    }
}
