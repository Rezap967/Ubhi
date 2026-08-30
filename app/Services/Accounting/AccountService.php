<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Repositories\Accounting\AccountRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(protected AccountRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Account>
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate(active_store_id(), $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Account>
     */
    public function search(array $filters = [], int $limit = 20): Collection
    {
        return $this->repository->search(active_store_id(), $filters, $limit);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Account
    {
        $storeId = active_store_id();

        $this->assertParentIsValid($data['parent_id'] ?? null, $data['type'], $storeId);

        return DB::transaction(fn (): Account => $this->repository->create([
            'store_id' => $storeId,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'normal_balance' => $this->normalBalanceFor($data['type']),
            'parent_id' => $data['parent_id'] ?? null,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]));
    }

    /** @param array<string, mixed> $data */
    public function update(Account $account, array $data): Account
    {
        $storeId = active_store_id();
        $this->assertBelongsToStore($account, $storeId);

        $type = $account->is_system ? $account->type : $data['type'];
        $this->assertParentIsValid($data['parent_id'] ?? null, $type, $storeId, $account->id);

        if (($data['is_active'] ?? true) === false) {
            $this->assertCanBeDeactivated($account);
        }

        return DB::transaction(fn (): Account => $this->repository->update($account, [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? $account->description,
            'type' => $type,
            'normal_balance' => $this->normalBalanceFor($type),
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $data['is_active'] ?? $account->is_active,
            'sort_order' => $data['sort_order'] ?? $account->sort_order,
        ]));
    }

    public function deactivate(Account $account): Account
    {
        $this->assertBelongsToStore($account, active_store_id());
        $this->assertCanBeDeactivated($account);

        return DB::transaction(fn (): Account => $this->repository->update($account, [
            'is_active' => false,
        ]));
    }

    protected function assertBelongsToStore(Account $account, int $storeId): void
    {
        if ((int) $account->store_id !== $storeId) {
            abort(404);
        }
    }

    protected function assertParentIsValid(?string $parentId, string $type, int $storeId, ?string $accountId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $accountId) {
            abort(422, 'Akun tidak boleh menjadi parent untuk dirinya sendiri.');
        }

        $parent = $this->repository->findForStore($parentId, $storeId);

        if ($parent === null || ! $parent->is_active) {
            abort(422, 'Parent akun tidak ditemukan atau tidak aktif.');
        }

        if ($parent->type !== $type) {
            abort(422, 'Tipe parent akun harus sama dengan tipe akun.');
        }
    }

    protected function assertCanBeDeactivated(Account $account): void
    {
        if ($account->is_system) {
            abort(409, 'Akun sistem tidak boleh dinonaktifkan.');
        }

        if ($account->activeChildren()->exists()) {
            abort(409, 'Akun tidak dapat dinonaktifkan karena masih memiliki child aktif.');
        }
    }

    protected function normalBalanceFor(string $type): string
    {
        return in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }
}
