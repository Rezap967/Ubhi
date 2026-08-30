<?php

// app/Repositories/Products/UnitRepository.php

namespace App\Repositories\Products;

use App\Models\Products\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UnitRepository
{
    /**
     * Whitelist kolom yang boleh dipakai untuk sorting — mencegah SQL injection via ORDER BY.
     *
     * TODO(product-relation): 'product_count' dihapus sementara dari whitelist karena
     * kolom ini dihasilkan dari JOIN ke tabel `products` yang di-stub. Kembalikan setelah
     * modul Product siap — lihat TODO-Pengembalian-Relasi-Product.md.
     */
    protected array $sortable = ['name', 'status'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductUnit::query()
            ->whereNull('deleted_at');

        if (! empty($filters['keyword'])) {
            $keyword = '%'.mb_strtolower($filters['keyword']).'%';
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('LOWER(name) LIKE ?', [$keyword])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$keyword]);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $sortBy = in_array($filters['sort_by'] ?? null, $this->sortable, true)
            ? $filters['sort_by']
            : 'name';

        $sortDirection = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortDirection);

        $perPage = (int) ($filters['limit'] ?? 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return $query->paginate($perPage);
    }

    public function findById(int $id): ProductUnit
    {
        $unit = ProductUnit::query()
            ->whereNull('deleted_at')
            ->find($id);

        if (! $unit) {
            throw new ModelNotFoundException("Satuan dengan ID {$id} tidak ditemukan.");
        }

        return $unit;
    }

    /**
     * TODO(product-relation): STUB SEMENTARA.
     * Selalu mengembalikan 0 untuk semua penggunaan karena tabel `products`,
     * `sales_items`, `purchase_items`, `stock_movements` belum tersedia/belum
     * diikutsertakan dalam relasi modul Satuan. Kembalikan query asli setelah
     * modul Product (dan modul transaksi terkait) siap — lihat
     * TODO-Pengembalian-Relasi-Product.md.
     *
     * Versi asli (jangan hapus, tinggal uncomment saat restore):
     *
     * return [
     *     'product_count'        => DB::table('products')->where('unit_id', $unitId)->whereNull('deleted_at')->count(),
     *     'sales_item_count'     => DB::table('sales_items')->where('unit_id', $unitId)->count(),
     *     'purchase_item_count'  => DB::table('purchase_items')->where('unit_id', $unitId)->count(),
     *     'stock_movement_count' => DB::table('stock_movements')->where('unit_id', $unitId)->count(),
     * ];
     */
    public function checkUsage(int $unitId): array
    {
        return [
            'product_count' => 0,
            'sales_item_count' => 0,
            'purchase_item_count' => 0,
            'stock_movement_count' => 0,
        ];
    }
}
