<?php

namespace App\Repositories\Customer\Eloquent;

use App\Models\Customer\Customer;
use App\Repositories\Customer\Contracts\CustomerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Kolom yang diizinkan untuk sorting, untuk mencegah SQL injection
     * lewat parameter sort_by.
     */
    private const ALLOWED_SORT_COLUMNS = [
        'name',
        'code',
        'phone',
        'email',
        'status',
        'address',
        'created_at',
    ];

    public function paginateByStore(int $storeId, array $filters = []): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null; // all/aktif/nonaktif
        $groupId = $filters['group_id'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = (int) ($filters['per_page'] ?? 20);

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }

        $query = Customer::query()
            ->select([
                'customers.id',
                'customers.code',
                'customers.name',
                'customers.phone',
                'customers.address',
                'customers.email',
                'customers.status',
                'customers.customer_group_id',
                'customers.created_at',
            ])
            ->selectRaw('customer_groups.name as group_name')
            ->leftJoin('customer_groups', function ($join) {
                $join->on('customer_groups.id', '=', 'customers.customer_group_id')
                    ->whereNull('customer_groups.deleted_at');
            })
            ->where('customers.store_id', $storeId)
            ->whereNull('customers.deleted_at');

        if (! empty($search)) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('customers.name', 'like', $like)
                    ->orWhere('customers.code', 'like', $like)
                    ->orWhere('customers.phone', 'like', $like)
                    ->orWhere('customers.email', 'like', $like);
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('customers.status', $status);
        }

        if (! empty($groupId)) {
            $query->where('customers.customer_group_id', $groupId);
        }

        $query->orderBy('customers.'.$sortBy, $sortDir);

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByIdAndStore(int $id, int $storeId): Customer
    {
        return Customer::query()
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->firstOrFail();
    }

    public function existsPhone(int $storeId, ?string $phone, ?int $ignoreId = null): bool
    {
        if (empty($phone)) {
            return false;
        }

        $query = Customer::query()
            ->where('store_id', $storeId)
            ->where('phone', $phone)
            ->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function existsEmail(int $storeId, ?string $email, ?int $ignoreId = null): bool
    {
        if (empty($email)) {
            return false;
        }

        $query = Customer::query()
            ->where('store_id', $storeId)
            ->where('email', $email)
            ->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->save();

        return $customer->fresh();
    }

    public function softDelete(Customer $customer, int $userId): bool
    {
        $customer->updated_by = $userId;
        $customer->save();

        return $customer->delete();
    }

    public function updateStatus(Customer $customer, string $status, int $userId): Customer
    {
        $customer->status = $status;
        $customer->updated_by = $userId;
        $customer->save();

        return $customer->fresh();
    }

    /**
     * Ambil nomor terakhir kode pelanggan untuk store tertentu,
     * dengan row lock (lockForUpdate) agar aman dari race condition.
     * WAJIB dipanggil di dalam DB::transaction() oleh Service.
     */
    public function getLastCodeNumberForStoreWithLock(int $storeId): int
    {
        // withTrashed() sengaja dipakai agar kode pelanggan yang sudah
        // di-soft-delete tetap dihitung, sehingga kode tidak pernah
        // dipakai ulang (menghindari bentrok dengan unique index code).
        $lastNumber = Customer::withTrashed()
            ->where('store_id', $storeId)
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_number')
            ->value('max_number');

        return (int) $lastNumber;
    }
}
