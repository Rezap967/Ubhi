<?php

namespace App\Repositories\Settings;

use App\Models\Settings\AdditionalFeeSetting;
use App\Repositories\Settings\Contracts\AdditionalFeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdditionalFeeRepository implements AdditionalFeeRepositoryInterface
{
    public function paginateByStore(int $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = AdditionalFeeSetting::query()
            ->forStore($storeId)
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->feeType($filters['fee_type'] ?? null);

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $allowSorts = ['sort_order', 'name', 'amount', 'status', 'fee_type', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowSorts, true)) {
            $sortBy = 'sort_order';
        }

        $query->orderBy($sortBy, $sortDir);
        if ($sortBy !== 'name') {
            $query->orderBy('name', 'asc');
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = min(max($perPage, 1), 100);

        return $query->paginate($perPage);
    }

    public function findByStore(int $storeId, int $id): ?AdditionalFeeSetting
    {
        return AdditionalFeeSetting::query()
            ->forStore($storeId)
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): AdditionalFeeSetting
    {
        return AdditionalFeeSetting::create($data);
    }

    public function update(AdditionalFeeSetting $fee, array $data): AdditionalFeeSetting
    {
        $fee->fill($data);
        $fee->save();
        return $fee->refresh();
    }

    public function delete(AdditionalFeeSetting $fee): bool
    {
        return (bool) $fee->delete();
    }

    public function existsNameInStore(int $storeId, string $name, ?int $ignoreId = null): bool
    {
        $query = AdditionalFeeSetting::query()
            ->forStore($storeId)
            ->where('name', $name);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}