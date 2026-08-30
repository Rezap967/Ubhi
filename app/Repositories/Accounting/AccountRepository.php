<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AccountRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Account>
     */
    public function paginate(int $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Account::query()
            ->forStore($storeId)
            ->with('parent')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->whereRaw('LOWER(code) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$search]);
                });
            })
            ->when(isset($filters['type']) && $filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Account>
     */
    public function search(int $storeId, array $filters = [], int $limit = 20): Collection
    {
        return Account::query()
            ->forStore($storeId)
            ->active()
            ->when($filters['q'] ?? $filters['search'] ?? null, function ($query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->whereRaw('LOWER(code) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$search]);
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('code')
            ->limit(min(max($limit, 1), 50))
            ->get();
    }

    public function findForStore(string $id, int $storeId): ?Account
    {
        return Account::query()->forStore($storeId)->whereKey($id)->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Account
    {
        return Account::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh(['parent']);
    }
}
