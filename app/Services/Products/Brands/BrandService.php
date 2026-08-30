<?php

// app/Services/Product/BrandService.php

namespace App\Services\Products\Brands;

use App\Exceptions\Brands\DuplicateBrandException;
use App\Models\Products\Brand;
use App\Services\Products\AuditLogService;
use Illuminate\Support\Facades\DB;

class BrandService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function create(array $data, int $userId): Brand
    {
        $this->assertNameNotDuplicate($data['name']);

        $brand = Brand::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $userId,
        ]);

        $this->auditLogService->log('brand.create', $brand);

        return $brand;
    }

    public function update(Brand $brand, array $data, int $userId): Brand
    {
        $this->assertNameNotDuplicate($data['name'], excludeId: $brand->id);

        $old = $brand->replicate();

        $brand->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? $brand->description,
            'status' => $data['status'] ?? $brand->status,
            'updated_by' => $userId,
        ]);
        $brand->save();

        $this->auditLogService->log('brand.update', $brand, $old);

        return $brand->fresh();
    }

    public function updateStatus(Brand $brand, string $status, int $userId): Brand
    {
        $brand->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        $this->auditLogService->log('brand.update_status', $brand);

        return $brand->fresh();
    }

    public function delete(Brand $brand, int $userId): void
    {
        // TODO: kembalikan pengecekan product_count setelah modul Product siap.
        // $productCount = Product::where('brand_id', $brand->id)->whereNull('deleted_at')->count();
        // if ($productCount > 0) { throw new BrandInUseException($productCount); }

        DB::transaction(function () use ($brand, $userId): void {
            $brand->update(['updated_by' => $userId]);
            $brand->delete();

            $this->auditLogService->log('brand.delete', $brand);
        });
    }

    protected function assertNameNotDuplicate(string $name, ?int $excludeId = null): void
    {
        $query = Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new DuplicateBrandException("Nama merek \"{$name}\" sudah digunakan.");
        }
    }
}
