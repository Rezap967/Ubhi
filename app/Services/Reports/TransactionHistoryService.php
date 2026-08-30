<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Repositories\Reports\TransactionHistoryRepository;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransactionHistoryService
{
    public function __construct(protected TransactionHistoryRepository $repository) {}

    public function getTransactions(User $user, array $filters)
    {
        $storeId = $user->getStoreId();
        if (! $storeId) {
            throw new AccessDeniedHttpException('User tidak memiliki toko terdaftar.');
        }

        // Cashier validation and forcing
        if ($user->hasRole('kasir')) {
            $reqCashierId = $filters['cashier_id'] ?? null;
            if ($reqCashierId !== null && (int) $reqCashierId !== $user->id) {
                throw ValidationException::withMessages([
                    'cashier_id' => ['Kasir hanya diperbolehkan melihat transaksi miliknya sendiri.'],
                ]);
            }
            $filters['cashier_id'] = $user->id;
        }

        return $this->repository->getTransactions($storeId, $filters, $user);
    }

    public function getTransactionDetail(User $user, int $transactionId)
    {
        $storeId = $user->getStoreId();
        if (! $storeId) {
            throw new AccessDeniedHttpException('User tidak memiliki toko terdaftar.');
        }

        return $this->repository->getTransactionDetail($storeId, $transactionId, $user);
    }

    public function getFilterOptions(User $user)
    {
        $storeId = $user->getStoreId();
        if (! $storeId) {
            throw new AccessDeniedHttpException('User tidak memiliki toko terdaftar.');
        }

        return $this->repository->getFilterOptions($storeId, $user);
    }
}
