<?php

// app/Services/Products/Categories/CategoryService.php

namespace App\Services\Products\Categories;

use App\Exceptions\Categories\CategoryInUseException;
use App\Exceptions\Categories\DuplicateCategoryException;
use App\Models\Products\Category;
use App\Services\Products\AuditLogService;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(
        protected CategoryValidationService $categoryValidationService,
        protected AuditLogService $auditLogService
    ) {}

    public function createCategory(array $data, int $userId): Category
    {
        $this->assertNameNotDuplicate($data['name']);

        $category = Category::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $userId,
        ]);

        $this->auditLogService->log('category.create', $category);

        return $category;
    }

    public function updateCategory(Category $category, array $data, int $userId): Category
    {
        $this->assertNameNotDuplicate($data['name'], excludeId: $category->id);

        $old = $category->replicate();
        $old->id = $category->id; // replicate() menghapus id — kembalikan secara eksplisit agar reference_id di audit log tidak null

        $category->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? $category->description,
            'status' => $data['status'] ?? $category->status,
            'updated_by' => $userId,
        ]);
        $category->save();

        $this->auditLogService->log('category.update', $category, $old);

        return $category->fresh();
    }

    public function updateStatus(Category $category, string $status, int $userId): Category
    {
        $category->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        $this->auditLogService->log('category.update_status', $category);

        return $category->fresh();
    }

    public function softDelete(Category $category, int $userId): void
    {
        // TODO(product-relation): pengecekan checkDeletable() di-nonaktifkan sementara
        // karena bergantung pada tabel `products` yang belum direlasikan ke modul ini.
        // Kembalikan setelah modul Product siap — lihat TODO-Pengembalian-Relasi-Product.md.
        //
        // $result = $this->categoryValidationService->checkDeletable($category->id);
        //
        // if (! $result['deletable']) {
        //     throw new CategoryInUseException($result['product_count']);
        // }

        DB::transaction(function () use ($category, $userId): void {
            $category->update(['updated_by' => $userId]);
            $category->delete();

            $this->auditLogService->log('category.delete', $category);
        });
    }

    protected function assertNameNotDuplicate(string $name, ?int $excludeId = null): void
    {
        $query = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new DuplicateCategoryException("Nama kategori \"{$name}\" sudah digunakan.");
        }
    }
}
