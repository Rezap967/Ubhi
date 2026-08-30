<?php

namespace App\Services\Kasir;

use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\User;
use App\Repositories\Kasir\PosSessionRepository;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OperationalSummaryService
{
    public function __construct(
        protected PosSessionRepository $sessionRepository,
        protected PricingService $pricingService,
    ) {}

    public function getActiveSession(User $user, string $deviceId): PosSession
    {
        $storeId = (int) $user->getStoreId();

        $session = $this->sessionRepository->findOpenSession(
            storeId: $storeId,
            userId: $user->id,
            deviceId: $deviceId
        );

        if (! $session) {
            throw new ConflictHttpException('Sesi kasir belum dibuka.');
        }

        return $session;
    }

    public function getFooterSummary(User $user, string $deviceId): array
    {
        $session = $this->getActiveSession($user, $deviceId);

        return $this->calculateSummaryForSession($session);
    }

    public function getSessionSummary(User $user, string $deviceId): array
    {
        // Untuk MVP, struktur sama dengan footer
        return $this->getFooterSummary($user, $deviceId);
    }

    public function getAllSessionsSummary(User $user, array $filters): array
    {
        $storeId = (int) ($filters['store_id'] ?? $user->getStoreId());
        $date = isset($filters['date']) ? Carbon::parse($filters['date'])->startOfDay() : now()->startOfDay();

        // Cari semua sesi pada hari tersebut untuk store ini
        $sessions = PosSession::query()
            ->where('store_id', $storeId)
            ->whereDate('opened_at', $date)
            ->get();

        $sessionIds = $sessions->pluck('id')->toArray();

        // Hitung transaksi dan payment untuk semua session_ids tersebut
        return $this->aggregateSessionsSummary($sessions, $sessionIds);
    }

    protected function calculateSummaryForSession(PosSession $session): array
    {
        $sessionId = $session->id;
        $storeId = $session->store_id;

        // Base query for transactions in this session
        $transactions = PosTransaction::query()
            ->where('session_id', $sessionId)
            ->where('store_id', $storeId);

        // Calculate transaction metrics (completed only)
        $completedTransactions = (clone $transactions)->where('status', 'completed');
        $transactionCount = $completedTransactions->count();
        $grossRevenue = (float) $completedTransactions->sum('subtotal');
        $discountTotal = (float) $completedTransactions->sum('discount_total');
        $taxTotal = (float) $completedTransactions->sum('tax_total');
        $netRevenue = (float) $completedTransactions->sum('grand_total');

        // Voided metrics
        $voidedTransactions = (clone $transactions)->where('status', 'voided');
        $voidedCount = $voidedTransactions->count();
        $voidedAmount = (float) $voidedTransactions->sum('grand_total');

        // Payment metrics
        // Only consider payments for completed transactions and where payment is paid
        $payments = PosPayment::query()
            ->whereHas('transaction', function ($q) use ($sessionId, $storeId) {
                $q->where('session_id', $sessionId)
                    ->where('store_id', $storeId)
                    ->where('status', 'completed');
            })
            ->where('status', 'paid')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->pluck('total', 'payment_method');

        $cashTotal = (float) ($payments['cash'] ?? 0);
        $qrisTotal = (float) ($payments['qris'] ?? 0);
        $debitTotal = (float) ($payments['debit'] ?? 0);
        $creditTotal = (float) ($payments['credit'] ?? 0);
        $transferTotal = (float) ($payments['transfer'] ?? 0);
        $ewalletTotal = (float) ($payments['ewallet'] ?? 0);

        $nonCashTotal = $qrisTotal + $debitTotal + $creditTotal + $transferTotal + $ewalletTotal;
        $expectedCash = (float) $session->opening_cash + $cashTotal;

        $durationSeconds = $this->calculateDurationSeconds($session);

        return [
            'session' => [
                'session_id' => $session->id,
                'status' => $session->status,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'duration_seconds' => $durationSeconds,
                'opening_cash' => (float) $session->opening_cash,
                'expected_cash' => $expectedCash,
            ],
            'transactions' => [
                'transaction_count' => $transactionCount,
                'gross_revenue' => $grossRevenue,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'net_revenue' => $netRevenue,
                'voided_count' => $voidedCount,
                'voided_amount' => $voidedAmount,
            ],
            'payments' => [
                'cash_total' => $cashTotal,
                'non_cash_total' => $nonCashTotal,
                'qris_total' => $qrisTotal,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'transfer_total' => $transferTotal,
                'ewallet_total' => $ewalletTotal,
            ],
            'formatted' => [
                'gross_revenue' => $this->formatMoney($grossRevenue),
                'net_revenue' => $this->formatMoney($netRevenue),
                'cash_total' => $this->formatMoney($cashTotal),
                'non_cash_total' => $this->formatMoney($nonCashTotal),
                'opening_cash' => $this->formatMoney($session->opening_cash),
                'expected_cash' => $this->formatMoney($expectedCash),
            ],
            'meta' => [
                'server_time' => now()->toIso8601String(),
                'app_version' => '1.0.0',
            ],
        ];
    }

    protected function aggregateSessionsSummary($sessions, array $sessionIds): array
    {
        // Simple aggregate for all sessions
        $transactions = PosTransaction::query()
            ->whereIn('session_id', $sessionIds)
            ->where('status', 'completed');

        $transactionCount = $transactions->count();
        $netRevenue = (float) $transactions->sum('grand_total');

        $cashTotal = (float) PosPayment::query()
            ->whereHas('transaction', function ($q) use ($sessionIds) {
                $q->whereIn('session_id', $sessionIds)->where('status', 'completed');
            })
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $totalOpeningCash = (float) $sessions->sum('opening_cash');

        return [
            'sessions_count' => $sessions->count(),
            'transaction_count' => $transactionCount,
            'net_revenue' => $netRevenue,
            'cash_total' => $cashTotal,
            'total_opening_cash' => $totalOpeningCash,
            'formatted' => [
                'net_revenue' => $this->formatMoney($netRevenue),
                'cash_total' => $this->formatMoney($cashTotal),
                'total_opening_cash' => $this->formatMoney($totalOpeningCash),
            ],
        ];
    }

    public function calculateDurationSeconds(PosSession $session): int
    {
        if (! $session->opened_at) {
            return 0;
        }

        $end = $session->closed_at ?? now();

        return abs($session->opened_at->diffInSeconds($end, false));
    }

    public function formatMoney(int|float|string $amount): string
    {
        return $this->pricingService->formatMoney($amount);
    }
}
