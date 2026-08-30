<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAssetCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixedAssetCategoryRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForStore(int $storeId, array $filters): LengthAwarePaginator
    {
        $query = FixedAssetCategory::query()
            ->forStore($storeId)
            ->with(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);

        $this->addAssetsCount($query, $storeId);

        $sorts = ['code', 'name', 'default_useful_life_months', 'assets_count', 'is_active', 'created_at'];
        $column = in_array($filters['sort_by'] ?? 'code', $sorts, true) ? $filters['sort_by'] : 'code';
        $direction = ($filters['sort_direction'] ?? $filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query
            ->when($filters['search'] ?? null, fn (Builder $builder, string $search) => $builder->where(fn (Builder $nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? null) === 'active' || ($filters['is_active'] ?? null) === true || ($filters['is_active'] ?? null) === 'true', fn (Builder $builder) => $builder->active())
            ->when(($filters['status'] ?? null) === 'inactive' || ($filters['is_active'] ?? null) === false || ($filters['is_active'] ?? null) === 'false', fn (Builder $builder) => $builder->inactive())
            ->when(($filters['depreciation_method'] ?? 'all') !== 'all', fn (Builder $builder) => $builder->where('depreciation_method', $filters['depreciation_method']))
            ->when($filters['asset_account_id'] ?? null, fn (Builder $builder, string $accountId) => $builder->where('asset_account_id', $accountId))
            ->orderBy($column, $direction)
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    public function findForStoreOrFail(int $storeId, string $id): FixedAssetCategory
    {
        $query = FixedAssetCategory::query()->forStore($storeId)->with(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);
        $this->addAssetsCount($query, $storeId);

        return $query->whereKey($id)->firstOrFail();
    }

    public function create(array $data): FixedAssetCategory
    {
        return FixedAssetCategory::query()->create($data);
    }

    public function update(FixedAssetCategory $category, array $data): FixedAssetCategory
    {
        $category->update($data);

        return $category->fresh(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);
    }

    /** @return array<string, int> */
    public function summary(int $storeId): array
    {
        $query = FixedAssetCategory::query()->forStore($storeId);
        $total = (clone $query)->count();
        $active = (clone $query)->active()->count();
        $inactive = (clone $query)->inactive()->count();

        if (! Schema::hasTable('fixed_assets')) {
            return ['total_categories' => $total, 'active_categories' => $active, 'inactive_categories' => $inactive, 'used_categories' => 0, 'unused_categories' => $total];
        }

        $used = (clone $query)->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('fixed_assets')->whereColumn('fixed_assets.category_id', 'fixed_asset_categories.id')->whereColumn('fixed_assets.store_id', 'fixed_asset_categories.store_id'))->count();

        return ['total_categories' => $total, 'active_categories' => $active, 'inactive_categories' => $inactive, 'used_categories' => $used, 'unused_categories' => $total - $used];
    }

    public function activeOptions(int $storeId): Collection
    {
        return FixedAssetCategory::query()->forStore($storeId)->active()->orderBy('name')->get();
    }

    public function assetAccountOptions(int $storeId): Collection
    {
        return Account::query()->forStore($storeId)->active()->where('type', 'asset')->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function isUsed(FixedAssetCategory $category): bool
    {
        return Schema::hasTable('fixed_assets') && DB::table('fixed_assets')->where('store_id', $category->store_id)->where('category_id', $category->id)->exists();
    }

    protected function addAssetsCount(Builder $query, int $storeId): void
    {
        if (Schema::hasTable('fixed_assets')) {
            $query->addSelect(['assets_count' => DB::table('fixed_assets')->selectRaw('count(*)')->whereColumn('fixed_assets.category_id', 'fixed_asset_categories.id')->where('fixed_assets.store_id', $storeId)]);
        } else {
            $query->addSelect(['assets_count' => DB::query()->selectRaw('0')]);
        }
    }
}
