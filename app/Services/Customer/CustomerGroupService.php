<?php

namespace App\Services\Customer;

use App\Models\Customer\CustomerGroup;
use App\Models\User;
use App\Repositories\Customer\Contracts\CustomerGroupRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerGroupService
{
    protected CustomerGroupRepositoryInterface $repository;

    public function __construct(CustomerGroupRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getPaginatedGroups(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($user->profile->store_id, $filters, $perPage);
    }

    public function getGroupById(int $id, User $user): ?CustomerGroup
    {
        return $this->repository->findById($id, $user->profile->store_id);
    }

    public function createGroup(User $user, array $data): CustomerGroup
    {
        $data['store_id'] = $user->profile->store_id;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        // Ensure discount_value is null if discount_type is empty
        if (empty($data['discount_type'])) {
            $data['discount_value'] = null;
        }

        // Additional validation for discount type and value already in request, but double-check
        if ($data['discount_type'] === 'persen' && isset($data['discount_value']) && $data['discount_value'] > 100) {
            throw new \InvalidArgumentException('Nilai diskon persen tidak boleh lebih dari 100.');
        }

        try {
            return DB::transaction(function () use ($data) {
                return $this->repository->create($data);
            });
        } catch (UniqueConstraintViolationException $e) {
            Log::warning('Duplicate group name attempt', ['store_id' => $data['store_id'], 'name' => $data['name']]);
            throw new ValidationException(
                Validator::make([], []),
                'Nama grup sudah digunakan di toko ini.'
            );
        }
    }

    public function updateGroup(User $user, CustomerGroup $group, array $data): CustomerGroup
    {
        // Ensure group belongs to user's store
        if ($group->store_id !== $user->profile->store_id) {
            abort(404);
        }

        $data['updated_by'] = $user->id;

        if (empty($data['discount_type'])) {
            $data['discount_value'] = null;
        }

        if ($data['discount_type'] === 'persen' && isset($data['discount_value']) && $data['discount_value'] > 100) {
            throw new \InvalidArgumentException('Nilai diskon persen tidak boleh lebih dari 100.');
        }

        try {
            return DB::transaction(function () use ($group, $data) {
                return $this->repository->update($group, $data);
            });
        } catch (UniqueConstraintViolationException $e) {
            Log::warning('Duplicate group name attempt on update', ['group_id' => $group->id, 'name' => $data['name']]);
            throw new ValidationException(
                Validator::make([], []),
                'Nama grup sudah digunakan di toko ini.'
            );
        }
    }

    public function deleteGroup(User $user, CustomerGroup $group, bool $confirm): void
    {
        if ($group->store_id !== $user->profile->store_id) {
            abort(404);
        }

        if (! $confirm) {
            abort(422, 'Konfirmasi hapus diperlukan.');
        }

        $activeCustomers = $this->repository->countActiveCustomers($group, $user->profile->store_id);
        if ($activeCustomers > 0) {
            abort(409, "Grup tidak dapat dihapus karena masih digunakan oleh {$activeCustomers} pelanggan aktif.");
        }

        DB::transaction(function () use ($group, $user) {
            $group->updated_by = $user->id;
            $group->save();
            $this->repository->delete($group);
        });
    }

    public function validateDelete(User $user, CustomerGroup $group): array
    {
        if ($group->store_id !== $user->profile->store_id) {
            abort(404);
        }

        $activeCustomers = $this->repository->countActiveCustomers($group, $user->profile->store_id);

        return [
            'can_delete' => $activeCustomers === 0,
            'affected_count' => $activeCustomers,
        ];
    }
}
