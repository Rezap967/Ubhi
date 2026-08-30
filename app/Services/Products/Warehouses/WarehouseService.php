<?php

// app/Services/Products/Warehouses/WarehouseService.php

namespace App\Services\Products\Warehouses;

use App\Exceptions\Warehouses\WarehouseCodeDuplicateException;
use App\Exceptions\Warehouses\WarehouseInUseException;
use App\Models\Products\Warehouse;
use App\Models\User;
use App\Services\Products\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseService
{
    protected array $sortable = ['name', 'address', 'warehouse_type'];

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function create(array $data, User $user): Warehouse
    {
        $this->assertCodeNotDuplicate($data['code'] ?? null);

        $store = $user->store;

        if (! $store) {
            throw new \RuntimeException('User tidak memiliki store.');
        }

        $data['store_id'] = $store->id;
        $data['warehouse_type'] = 'warehouse';
        $data['created_by'] = $user->id;

        $warehouse = Warehouse::query()->create($data);

        $this->auditLogService->log('warehouse.create', $warehouse);

        return $warehouse;
    }

    public function update(Warehouse $warehouse, array $data, int $userId): Warehouse
    {
        $this->assertCodeNotDuplicate($data['code'] ?? null, excludeId: $warehouse->id);

        $old = $warehouse->replicate();
        $old->id = $warehouse->id; // replicate() menghapus id — kembalikan secara eksplisit agar reference_id di audit log tidak null

        $data['updated_by'] = $userId;
        $warehouse->update($data);

        $this->auditLogService->log('warehouse.update', $warehouse, $old);

        return $warehouse->fresh();
    }

    public function delete(Warehouse $warehouse, int $userId): void
    {
        // TODO(product-relation): pengecekan stock_movements bergantung pada tabel
        // yang mungkin belum tersedia. Jika tabel belum ada, stub sementara di bawah
        // ini bisa dipakai — lihat TODO-Pengembalian-Relasi-Product.md untuk detail restore.
        $usageCount = 0;
        if (Schema::hasTable('stock_movements')) {
            $usageCount = DB::table('stock_movements')
                ->where('warehouse_id', $warehouse->id)
                ->count();
        }

        if ($usageCount > 0) {
            $this->auditLogService->log('warehouse.delete_rejected', $warehouse);

            throw new WarehouseInUseException;
        }

        DB::transaction(function () use ($warehouse): void {
            $warehouse->delete();

            $this->auditLogService->log('warehouse.delete', $warehouse);
        });
    }

    public function getList(array $filters, User $user): LengthAwarePaginator
    {
        $keyword = $filters['keyword'] ?? null;

        $sortBy = in_array($filters['sort_by'] ?? null, $this->sortable, true)
            ? $filters['sort_by']
            : 'name';

        $sortDirection = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $limit = (int) ($filters['limit'] ?? 20);
        $limit = $limit > 0 && $limit <= 100 ? $limit : 20;

        $store = $user->store;

        if (! $store) {
            throw new \RuntimeException('User tidak memiliki store.');
        }

        return Warehouse::query()
            ->where('store_id', $store->id)
            ->when($keyword, function ($q) use ($keyword) {
                $kw = '%'.mb_strtolower($keyword).'%';

                $q->where(function ($sub) use ($kw) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$kw])
                        ->orWhereRaw('LOWER(COALESCE(code, "")) LIKE ?', [$kw])
                        ->orWhereRaw('LOWER(COALESCE(address, "")) LIKE ?', [$kw])
                        ->orWhereRaw('LOWER(COALESCE(phone, "")) LIKE ?', [$kw]);
                });
            })
            ->whereNull('deleted_at')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($limit);
    }

    protected function assertCodeNotDuplicate(?string $code, ?int $excludeId = null): void
    {
        if (empty($code)) {
            return;
        }

        $query = Warehouse::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new WarehouseCodeDuplicateException;
        }
    }
}
