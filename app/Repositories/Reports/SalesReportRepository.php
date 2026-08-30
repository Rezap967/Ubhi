<?php

namespace App\Repositories\Reports;

use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosTransaction;
use App\Models\Kasir\PosTransactionItem;
use Illuminate\Support\Facades\DB;

class SalesReportRepository
{
    public function getSummary(int $storeId, array $filters): array
    {
        // Query transaksi completed
        $query = PosTransaction::query()
            ->where('store_id', $storeId)
            ->where('status', 'completed')  // Hanya completed
            ->when($filters['date_start'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '>=', $d))
            ->when($filters['date_end'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '<=', $d))
            ->when($filters['cashier_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['payment_method'] ?? null, function ($q, $method) {
                return $q->whereHas('payments', fn ($q) => $q->where('payment_method', $method));
            })
            ->when($filters['product_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items', fn ($q) => $q->where('product_id', $id));
            })
            ->when($filters['category_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items.product', fn ($q) => $q->where('category_id', $id));
            });

        $summary = (clone $query)
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(grand_total) as gross_sales,
                SUM(grand_total - discount_total - tax_total) as net_sales
            ')
            ->first();

        // Total voided
        $voidTotal = PosTransaction::query()
            ->where('store_id', $storeId)
            ->where('status', 'voided')
            ->when($filters['date_start'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '>=', $d))
            ->when($filters['date_end'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '<=', $d))
            ->when($filters['cashier_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['payment_method'] ?? null, function ($q, $method) {
                return $q->whereHas('payments', fn ($q) => $q->where('payment_method', $method));
            })
            ->when($filters['product_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items', fn ($q) => $q->where('product_id', $id));
            })
            ->when($filters['category_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items.product', fn ($q) => $q->where('category_id', $id));
            })
            ->sum('grand_total');

        $transactionCount = (int) ($summary->transaction_count ?? 0);
        $netSales = (float) ($summary->net_sales ?? 0);

        return [
            'transaction_count' => $transactionCount,
            'gross_sales' => (float) ($summary->gross_sales ?? 0),
            'net_sales' => $netSales,
            'average_order_value' => $transactionCount > 0 ? $netSales / $transactionCount : 0,
            'refund_or_void_total' => (float) $voidTotal,
        ];
    }

    public function getTopProducts(int $storeId, array $filters): array
    {
        return PosTransactionItem::query()
            ->join('pos_transactions', 'pos_transaction_items.transaction_id', '=', 'pos_transactions.id')
            ->join('products', 'pos_transaction_items.product_id', '=', 'products.id')
            ->where('pos_transactions.store_id', $storeId)
            ->where('pos_transactions.status', 'completed')
            ->when($filters['date_start'] ?? null, fn ($q, $d) => $q->whereDate('pos_transactions.transaction_at', '>=', $d))
            ->when($filters['date_end'] ?? null, fn ($q, $d) => $q->whereDate('pos_transactions.transaction_at', '<=', $d))
            ->when($filters['cashier_id'] ?? null, fn ($q, $id) => $q->where('pos_transactions.user_id', $id))
            ->when($filters['payment_method'] ?? null, function ($q, $method) {
                return $q->whereHas('transaction.payments', fn ($q) => $q->where('payment_method', $method));
            })
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('products.category_id', $id))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('pos_transaction_items.product_id', $id))
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                SUM(pos_transaction_items.quantity) as qty_sold,
                SUM(pos_transaction_items.subtotal) as net_sales
            ')
            ->groupBy('products.id', 'products.name')
            ->orderBy('qty_sold', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getPaymentBreakdown(int $storeId, array $filters): array
    {
        return PosPayment::query()
            ->join('pos_transactions', 'pos_payments.transaction_id', '=', 'pos_transactions.id')
            ->where('pos_transactions.store_id', $storeId)
            ->where('pos_transactions.status', 'completed')
            ->when($filters['date_start'] ?? null, fn ($q, $d) => $q->whereDate('pos_transactions.transaction_at', '>=', $d))
            ->when($filters['date_end'] ?? null, fn ($q, $d) => $q->whereDate('pos_transactions.transaction_at', '<=', $d))
            ->when($filters['cashier_id'] ?? null, fn ($q, $id) => $q->where('pos_transactions.user_id', $id))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('pos_payments.payment_method', $method))
            ->when($filters['product_id'] ?? null, function ($q, $id) {
                return $q->whereHas('transaction.items', fn ($q) => $q->where('product_id', $id));
            })
            ->when($filters['category_id'] ?? null, function ($q, $id) {
                return $q->whereHas('transaction.items.product', fn ($q) => $q->where('category_id', $id));
            })
            ->selectRaw('payment_method as method, SUM(amount) as amount')
            ->groupBy('payment_method')
            ->orderBy('amount', 'desc')
            ->get()
            ->toArray();
    }

    public function getSalesTrend(int $storeId, array $filters): array
    {
        $granularity = $filters['granularity'] ?? 'day';

        // Pilih ekspresi format tanggal sesuai driver DB
        // MySQL: DATE_FORMAT() | SQLite (test): strftime()
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $dateExpr = match ($granularity) {
                'week' => "strftime('%Y-%W', transaction_at)",
                'month' => "strftime('%Y-%m', transaction_at)",
                default => "strftime('%Y-%m-%d', transaction_at)",
            };
        } else {
            $mysqlFormat = match ($granularity) {
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };
            $dateExpr = "DATE_FORMAT(transaction_at, '{$mysqlFormat}')";
        }

        return PosTransaction::query()
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->when($filters['date_start'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '>=', $d))
            ->when($filters['date_end'] ?? null, fn ($q, $d) => $q->whereDate('transaction_at', '<=', $d))
            ->when($filters['cashier_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['payment_method'] ?? null, function ($q, $method) {
                return $q->whereHas('payments', fn ($q) => $q->where('payment_method', $method));
            })
            ->when($filters['product_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items', fn ($q) => $q->where('product_id', $id));
            })
            ->when($filters['category_id'] ?? null, function ($q, $id) {
                return $q->whereHas('items.product', fn ($q) => $q->where('category_id', $id));
            })
            ->selectRaw("
                {$dateExpr} as date,
                SUM(grand_total) as gross_sales,
                SUM(grand_total - discount_total - tax_total) as net_sales,
                COUNT(*) as transaction_count
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }
}
