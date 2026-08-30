<?php

// app/Services/Products/ProductService.php

namespace App\Services\Products;

use App\Exceptions\Products\DuplicateSkuException;
use App\Exceptions\Products\ProductInUseException;
use App\Models\Products\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    // -------------------------------------------------------------------------
    // SF-03 — List Products (dengan filter, sort, paginate)
    // -------------------------------------------------------------------------

    /**
     * @param  array{
     *   keyword?: string|null,
     *   status?: string,
     *   category_id?: int|null,
     *   sort_by?: string,
     *   sort_direction?: string,
     *   limit?: int,
     * } $filters
     */
    public function listProducts(
        array $filters,
        string $role,
        int $storeId
    ): LengthAwarePaginator {
        $keyword = $filters['keyword'] ?? null;
        $status = $filters['status'] ?? 'semua';
        $categoryId = $filters['category_id'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $limit = $filters['limit'] ?? 15;

        if ($role === 'kasir') {
            $status = 'aktif';
        }

        $query = Product::query()
            ->where('store_id', $storeId)
            ->parentOnly()
            ->withStatus($status)
            ->search($keyword)
            ->withCategory($categoryId)
            ->with([
                'category',
                'brand',
                'unit',
                'children.unit',
                'images',
                'specialPrices',
            ])
            ->orderBy($sortBy, $sortDirection);

        return $query->paginate($limit);
    }

    // -------------------------------------------------------------------------
    // SF-05 — Create Product
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data, int $userId): Product
    {
        if (isset($data['sku']) && $data['sku'] !== null) {
            $this->assertSkuNotDuplicate($data['sku']);
        }

        $user = User::findOrFail($userId);
        $store = $user->ownedStores()->firstOrFail();

        return DB::transaction(function () use ($data, $userId, $store): Product {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'unit_id' => $data['unit_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'type' => $data['type'],
                'sell_price' => $data['sell_price'],
                'buy_price' => $data['buy_price'],
                'initial_stock' => $data['initial_stock'] ?? 0,
                'status' => $data['status'] ?? 'aktif',
                'created_by' => $userId,
            ]);

            // Lampirkan pemasok jika ada
            if (! empty($data['supplier_ids'])) {
                $product->suppliers()->sync($data['supplier_ids']);
            }

            // Simpan varian produk (anak)
            if ($data['type'] === 'variant' && ! empty($data['satuans'])) {
                foreach ($data['satuans'] as $satuan) {
                    if (isset($satuan['isDefault']) && $satuan['isDefault'] === true) {
                        continue; // Lewati parent
                    }

                    if (isset($satuan['satuan'])) {
                        Product::create([
                            'store_id' => $store->id,
                            'parent_id' => $product->id,
                            'category_id' => $data['category_id'],
                            'brand_id' => $data['brand_id'] ?? null,
                            'unit_id' => $satuan['satuan'],
                            'name' => $data['name'],
                            'description' => $data['description'] ?? null,
                            'sku' => $satuan['sku'] ?? null,
                            'barcode' => $satuan['barcode'] ?? null,
                            'type' => 'variant',
                            'sell_price' => $satuan['hargaJual'],
                            'buy_price' => $satuan['hargaBeli'] ?? 0,
                            'initial_stock' => $satuan['stokAwal'] ?? 0,
                            'status' => $data['status'] ?? 'aktif',
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            $this->auditLogService->log('product.create', $product);

            return $product->load(['category', 'brand', 'unit', 'images', 'specialPrices', 'suppliers']);
        });
    }

    // -------------------------------------------------------------------------
    // SF-06 — Update Product
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data, int $userId): Product
    {
        $user = User::findOrFail($userId);
        $store = $user->ownedStores()->firstOrFail();

        if ((int) $product->store_id !== (int) $store->id) {
            abort(403, 'Produk bukan milik toko Anda.');
        }

        if (isset($data['sku']) && $data['sku'] !== null) {
            $this->assertSkuNotDuplicate($data['sku'], excludeId: $product->id);
        }

        return DB::transaction(function () use ($product, $data, $userId, $store): Product {
            $old = $product->replicate();
            $old->id = $product->id;

            $product->fill([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? $product->brand_id,
                'unit_id' => $data['unit_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? $product->description,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'type' => $data['type'],
                'sell_price' => $data['sell_price'],
                'buy_price' => $data['buy_price'],
                'initial_stock' => $data['initial_stock'] ?? $product->initial_stock,
                'status' => $data['status'] ?? $product->status,
                'updated_by' => $userId,
            ]);
            $product->save();

            if (array_key_exists('supplier_ids', $data)) {
                $product->suppliers()->sync($data['supplier_ids'] ?? []);
            }

            // Sinkronisasi varian (anak)
            if ($data['type'] === 'variant' && ! empty($data['satuans'])) {
                $existingChildren = $product->children()->get()->keyBy('unit_id');
                $newUnitIds = [];

                foreach ($data['satuans'] as $satuan) {
                    if (isset($satuan['isDefault']) && $satuan['isDefault'] === true) {
                        continue; // Lewati parent
                    }

                    if (isset($satuan['satuan'])) {
                        $unitId = $satuan['satuan'];
                        $newUnitIds[] = $unitId;

                        if ($existingChildren->has($unitId)) {
                            $child = $existingChildren->get($unitId);
                            $child->update([
                                'sku' => $satuan['sku'] ?? null,
                                'barcode' => $satuan['barcode'] ?? null,
                                'sell_price' => $satuan['hargaJual'],
                                'buy_price' => $satuan['hargaBeli'] ?? 0,
                                'initial_stock' => $satuan['stokAwal'] ?? 0,
                                'updated_by' => $userId,
                            ]);
                        } else {
                            Product::create([
                                'store_id' => $store->id,
                                'parent_id' => $product->id,
                                'category_id' => $data['category_id'],
                                'brand_id' => $data['brand_id'] ?? null,
                                'unit_id' => $unitId,
                                'name' => $data['name'],
                                'description' => $data['description'] ?? null,
                                'sku' => $satuan['sku'] ?? null,
                                'barcode' => $satuan['barcode'] ?? null,
                                'type' => 'variant',
                                'sell_price' => $satuan['hargaJual'],
                                'buy_price' => $satuan['hargaBeli'] ?? 0,
                                'initial_stock' => $satuan['stokAwal'] ?? 0,
                                'status' => $data['status'] ?? 'aktif',
                                'created_by' => $userId,
                            ]);
                        }
                    }
                }

                // Hapus varian yang sudah tidak ada
                $product->children()->whereNotIn('unit_id', $newUnitIds)->delete();
            } elseif ($data['type'] === 'single') {
                // Jika diubah menjadi single, hapus semua anak
                $product->children()->delete();
            }

            $this->auditLogService->log('product.update', $product, $old);

            return $product->fresh(['category', 'brand', 'unit', 'images', 'specialPrices', 'suppliers']);
        });
    }

    // -------------------------------------------------------------------------
    // SF-07 — Update Status
    // -------------------------------------------------------------------------

    public function updateStatus(Product $product, string $status, int $userId): Product
    {
        $user = User::findOrFail($userId);
        $store = $user->ownedStores()->firstOrFail();

        if ((int) $product->store_id !== (int) $store->id) {
            abort(403, 'Produk bukan milik toko Anda.');
        }

        $product->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        $this->auditLogService->log('product.update_status', $product);

        return $product->fresh();
    }

    // -------------------------------------------------------------------------
    // SF-07 — Check Transaction History (sebelum hapus permanen)
    // -------------------------------------------------------------------------

    /**
     * Kembalikan true jika produk memiliki histori di transaction_items.
     * Jika tabel belum ada, return false (aman dihapus).
     */
    public function hasTransactionHistory(Product $product): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('transaction_items')) {
            return false;
        }

        return DB::table('transaction_items')
            ->where('product_id', $product->id)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // SF-07 — Soft Delete Product
    // -------------------------------------------------------------------------

    public function deleteProduct(Product $product, int $userId): void
    {
        $user = User::findOrFail($userId);
        $store = $user->ownedStores()->firstOrFail();

        if ((int) $product->store_id !== (int) $store->id) {
            abort(403, 'Produk bukan milik toko Anda.');
        }

        if ($this->hasTransactionHistory($product)) {
            throw new ProductInUseException(
                DB::table('transaction_items')
                    ->where('product_id', $product->id)
                    ->count()
            );
        }

        DB::transaction(function () use ($product, $userId): void {
            $product->update(['updated_by' => $userId]);
            $product->delete();

            $this->auditLogService->log('product.delete', $product);
        });
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function assertSkuNotDuplicate(string $sku, ?int $excludeId = null): void
    {
        $query = Product::withTrashed()
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower(trim($sku))])
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new DuplicateSkuException($sku);
        }
    }
}
