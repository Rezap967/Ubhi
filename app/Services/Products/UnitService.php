<?php

// app/Services/Product/UnitService.php

namespace App\Services\Products;

use App\Exceptions\DuplicateUnitException;
use App\Exceptions\UnitInUseException;
use App\Models\Products\ProductUnit;
use App\Repositories\Products\UnitRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function __construct(
        protected UnitRepository $unitRepository,
        protected UnitValidationService $unitValidationService,
        protected AuditLogService $auditLogService
    ) {}

    public function getList(array $filters): LengthAwarePaginator
    {
        return $this->unitRepository->paginate($filters);
    }

    public function create(array $data, int $userId): ProductUnit
    {
        $this->assertNameNotDuplicate($data['name']);

        $unit = ProductUnit::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $userId,
        ]);

        $this->auditLogService->log('unit.create', $unit);

        return $unit;
    }

    public function update(ProductUnit $unit, array $data, int $userId): ProductUnit
    {
        $this->assertNameNotDuplicate($data['name'], excludeId: $unit->id);

        $old = $unit->replicate();
        $old->id = $unit->id;

        $unit->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? $unit->description,
            'status' => $data['status'] ?? $unit->status,
            'updated_by' => $userId,
        ]);
        $unit->save();

        $this->auditLogService->log('unit.update', $old, $unit);

        return $unit->fresh();
    }

    public function updateStatus(ProductUnit $unit, string $status, int $userId): ProductUnit
    {
        $unit->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        $this->auditLogService->log('unit.update_status', $unit);

        return $unit->fresh();
    }

    public function delete(ProductUnit $unit, int $userId): void
    {
        $usage = $this->unitValidationService->checkUsage($unit->id);

        if ($usage['in_use']) {
            throw new UnitInUseException($usage);
        }

        DB::transaction(function () use ($unit, $userId): void {
            $unit->update(['updated_by' => $userId]);
            $unit->delete(); // soft delete

            $this->auditLogService->log('unit.delete', $unit);
        });
    }

    /**
     * Cek duplikasi nama secara case-insensitive.
     * $excludeId dipakai saat update, supaya record itu sendiri tidak dihitung duplikat.
     */
    protected function assertNameNotDuplicate(string $name, ?int $excludeId = null): void
    {
        $query = ProductUnit::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new DuplicateUnitException("Nama satuan \"{$name}\" sudah digunakan.");
        }
    }
}
