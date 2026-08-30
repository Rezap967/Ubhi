<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAssetCategory;
use App\Repositories\Accounting\FixedAssetCategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetCategoryService
{
    public function __construct(protected FixedAssetCategoryRepository $repository) {}

    public function list(int $storeId, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateForStore($storeId, $filters);
    }

    public function create(int $storeId, array $payload): FixedAssetCategory
    {
        return DB::transaction(function () use ($storeId, $payload): FixedAssetCategory {
            $this->validateAccounts($storeId, $payload);
            $category = $this->repository->create([...$payload, 'store_id' => $storeId, 'created_by' => Auth::id(), 'updated_by' => Auth::id()]);

            return $this->repository->findForStoreOrFail($storeId, $category->id);
        });
    }

    public function update(int $storeId, FixedAssetCategory $category, array $payload): FixedAssetCategory
    {
        $this->validateBelongsToStore($storeId, $category);

        return DB::transaction(function () use ($storeId, $category, $payload): FixedAssetCategory {
            if ($category->is_system && (($payload['code'] ?? $category->code) !== $category->code || ($payload['depreciation_method'] ?? $category->depreciation_method) !== $category->depreciation_method)) {
                throw ValidationException::withMessages(['category' => 'Kategori sistem tidak dapat mengubah kode atau metode depresiasi.']);
            }

            $this->validateAccounts($storeId, $payload);

            return $this->repository->update($category, [...$payload, 'updated_by' => Auth::id()]);
        });
    }

    public function delete(int $storeId, FixedAssetCategory $category): void
    {
        $this->validateBelongsToStore($storeId, $category);

        DB::transaction(function () use ($category): void {
            if ($category->is_system) {
                throw ValidationException::withMessages(['category' => 'Kategori sistem tidak dapat dihapus.']);
            }
            if ($this->repository->isUsed($category)) {
                throw ValidationException::withMessages(['category' => 'Kategori aset tetap sudah digunakan dan tidak dapat dihapus.']);
            }

            $category->delete();
        });
    }

    public function updateStatus(int $storeId, FixedAssetCategory $category, bool $isActive): FixedAssetCategory
    {
        $this->validateBelongsToStore($storeId, $category);
        if ($category->is_system && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => 'Kategori sistem tidak dapat dinonaktifkan.']);
        }

        return DB::transaction(fn (): FixedAssetCategory => $this->repository->update($category, ['is_active' => $isActive, 'updated_by' => Auth::id()]));
    }

    public function validateBelongsToStore(int $storeId, FixedAssetCategory $category): void
    {
        abort_unless((int) $category->store_id === $storeId, 404);
    }

    public function validateAccounts(int $storeId, array $payload): void
    {
        $accountIds = array_values(array_filter([$payload['asset_account_id'] ?? null, $payload['accumulated_depreciation_account_id'] ?? null, $payload['depreciation_expense_account_id'] ?? null]));
        $valid = Account::query()->forStore($storeId)->active()->whereIn('id', $accountIds)->get()->keyBy('id');

        if (($payload['asset_account_id'] ?? null) && (($valid->get($payload['asset_account_id'])->type ?? null) !== 'asset')) {
            throw ValidationException::withMessages(['asset_account_id' => 'Akun aset tidak valid atau bukan milik toko aktif.']);
        }
        if (($payload['accumulated_depreciation_account_id'] ?? null) && (($valid->get($payload['accumulated_depreciation_account_id'])->type ?? null) !== 'asset')) {
            throw ValidationException::withMessages(['accumulated_depreciation_account_id' => 'Akun akumulasi depresiasi tidak valid.']);
        }
        if (($payload['depreciation_expense_account_id'] ?? null) && (($valid->get($payload['depreciation_expense_account_id'])->type ?? null) !== 'expense')) {
            throw ValidationException::withMessages(['depreciation_expense_account_id' => 'Akun beban depresiasi tidak valid.']);
        }
    }

    public function isUsed(FixedAssetCategory $category): bool
    {
        return $this->repository->isUsed($category);
    }
}
