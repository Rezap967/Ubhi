<?php

namespace App\Repositories\Reports;

use App\Models\Customer\Customer;
use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosTransaction;
use App\Models\User;

class TransactionHistoryRepository
{
    public function getTransactions(int $storeId, array $filters, User $user)
    {
        $query = PosTransaction::query()
            ->with(['payments', 'user', 'customer'])
            ->where('store_id', $storeId);

        // Kasir restriction
        if ($user->hasRole('kasir')) {
            $query->where('user_id', $user->id);
        } elseif (! empty($filters['cashier_id'])) {
            $query->where('user_id', $filters['cashier_id']);
        }

        // Filters
        if (! empty($filters['date_start'])) {
            $query->whereDate('transaction_at', '>=', $filters['date_start']);
        }
        if (! empty($filters['date_end'])) {
            $query->whereDate('transaction_at', '<=', $filters['date_end']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['customer_id'])) {
            // Pelanggan "UMUM" (code MBR-000000) disimpan sebagai customer_id = NULL
            // di pos_transactions (lihat CustomerSelectorDropdown.vue: selectCustomer()).
            // Kalau filter yang dipilih adalah id UMUM, cari transaksi yang customer_id-nya
            // NULL, bukan cari customer_id = id UMUM (yang tidak akan pernah match).
            $umumCustomerId = Customer::where('store_id', $storeId)
                ->where('code', 'MBR-000000')
                ->value('id');

            if ($umumCustomerId !== null && (int) $filters['customer_id'] === (int) $umumCustomerId) {
                $query->whereNull('customer_id');
            } else {
                $query->where('customer_id', $filters['customer_id']);
            }
        }
        if (! empty($filters['receipt_no'])) {
            $query->where('transaction_number', 'like', '%'.$filters['receipt_no'].'%');
        }
        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', function ($q) use ($filters) {
                $q->where('payment_method', $filters['payment_method']);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'transaction_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        if ($sortBy === 'receipt_no') {
            $sortBy = 'transaction_number';
        }

        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    public function getTransactionDetail(int $storeId, int $transactionId, User $user): ?PosTransaction
    {
        $query = PosTransaction::query()
            ->with(['items', 'payments', 'user', 'customer'])
            ->where('store_id', $storeId)
            ->where('id', $transactionId);

        // Kasir restriction
        if ($user->hasRole('kasir')) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    public function getFilterOptions(int $storeId, User $user): array
    {
        // Cashiers
        if ($user->hasRole('kasir')) {
            $cashiers = collect([
                [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ]);
        } else {
            $cashiers = User::whereHas('profile', fn ($q) => $q->where('store_id', $storeId))
                ->whereHas('role', fn ($q) => $q->where('name', 'kasir'))
                ->select('id', 'name')
                ->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);
        }

        // Distinct payment methods
        $paymentMethods = PosPayment::whereHas('transaction', fn ($q) => $q->where('store_id', $storeId))
            ->distinct()
            ->pluck('payment_method')
            ->values()
            ->all();

        // Distinct statuses from DB + completed/voided default fallback
        $dbStatuses = PosTransaction::where('store_id', $storeId)
            ->distinct()
            ->pluck('status')
            ->values()
            ->all();
        $statuses = array_values(array_unique(array_merge($dbStatuses, ['completed', 'voided'])));

        // Store active customers
        $customers = Customer::where('store_id', $storeId)
            ->select('id', 'name')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        return [
            'cashiers' => $cashiers,
            'statuses' => $statuses,
            'payment_methods' => $paymentMethods,
            'customers' => $customers,
        ];
    }
}