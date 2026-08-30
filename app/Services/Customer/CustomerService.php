<?php

namespace App\Services\Customer;

use App\Models\Customer\Customer;
use App\Repositories\Customer\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository
    ) {}

    public function getList(int $storeId, array $filters = []): LengthAwarePaginator
    {
        return $this->customerRepository->paginateByStore($storeId, $filters);
    }

    public function findById(int $id, int $storeId): Customer
    {
        return $this->customerRepository->findByIdAndStore($id, $storeId);
    }

    public function store(int $storeId, int $userId, array $data): Customer
    {
        try {
            return DB::transaction(function () use ($storeId, $userId, $data) {
                $code = $this->generateCode($storeId);

                $payload = array_merge($data, [
                    'store_id' => $storeId,
                    'code' => $code,
                    'status' => 'aktif',
                    'created_by' => $userId,
                    'updated_by' => null,
                ]);

                return $this->customerRepository->create($payload);
            });
        } catch (QueryException $e) {
            $this->handleIntegrityViolation($e);
        }
    }

    public function update(int $id, int $storeId, int $userId, array $data): Customer
    {
        $customer = $this->customerRepository->findByIdAndStore($id, $storeId);

        // code tidak boleh diubah dari client walaupun ikut terkirim.
        unset($data['code']);

        $data['updated_by'] = $userId;

        try {
            return DB::transaction(function () use ($customer, $data) {
                return $this->customerRepository->update($customer, $data);
            });
        } catch (QueryException $e) {
            $this->handleIntegrityViolation($e);
        }
    }

    public function delete(int $id, int $storeId, int $userId, bool $confirm = false): bool
    {
        if (! $confirm) {
            throw ValidationException::withMessages([
                'confirm' => 'Konfirmasi penghapusan wajib diberikan.',
            ]);
        }

        $customer = $this->customerRepository->findByIdAndStore($id, $storeId);

        // Soft delete saja, tidak boleh hard delete.
        return $this->customerRepository->softDelete($customer, $userId);
    }

    public function updateStatus(int $id, int $storeId, int $userId, string $status): Customer
    {
        $customer = $this->customerRepository->findByIdAndStore($id, $storeId);

        return $this->customerRepository->updateStatus($customer, $status, $userId);
    }

    public function quickCreateFromPOS(int $storeId, int $userId, array $data): Customer
    {
        try {
            return DB::transaction(function () use ($storeId, $userId, $data) {
                $code = $this->generateCode($storeId);

                $payload = [
                    'store_id' => $storeId,
                    'code' => $code,
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'customer_group_id' => null,
                    'status' => 'aktif',
                    'created_by' => $userId,
                    'updated_by' => null,
                ];

                return $this->customerRepository->create($payload);
            });
        } catch (QueryException $e) {
            $this->handleIntegrityViolation($e);
        }
    }

    /**
     * Generate kode pelanggan sequential per store, race-condition safe.
     * WAJIB dipanggil di dalam DB::transaction() milik pemanggil,
     * karena getLastCodeNumberForStoreWithLock() memakai lockForUpdate().
     *
     * Format: MBR-000001, MBR-000002, dst.
     */
    private function generateCode(int $storeId): string
    {
        try {
            $lastNumber = $this->customerRepository->getLastCodeNumberForStoreWithLock($storeId);
            $nextNumber = $lastNumber + 1;

            return 'MBR-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'code' => 'Gagal membuat kode pelanggan. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Terjemahkan QueryException (duplicate key / FK violation) menjadi
     * pesan Bahasa Indonesia yang ramah pengguna.
     *
     * @throws ValidationException
     */
    private function handleIntegrityViolation(QueryException $e): never
    {
        // SQLSTATE 23000 = integrity constraint violation (duplicate/FK).
        if ($e->getCode() === '23000') {
            throw ValidationException::withMessages([
                'general' => 'Data pelanggan gagal disimpan karena terdapat data duplikat. '
                    .'Silakan periksa kembali nomor telepon/email.',
            ]);
        }

        throw $e;
    }
}
