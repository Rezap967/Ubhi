<?php

namespace App\Services\Kasir;

use App\Exceptions\Kasir\PosSessionAlreadyActiveException;
use App\Exceptions\Kasir\PosSessionAlreadyClosedException;
use App\Models\Kasir\PosAuditLog;
use App\Models\Kasir\PosSession;
use App\Models\User;
use App\Repositories\Kasir\PosCartRepository;
use App\Repositories\Kasir\PosSessionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PosSessionService
{
    public function __construct(
        protected PosSessionRepository $sessionRepository,
        protected PosCartRepository $cartRepository,
    ) {}

    /**
     * Ambil sesi POS aktif milik user pada store dan device tertentu.
     */
    public function getCurrentSession(User $user, string $deviceId): ?PosSession
    {
        $storeId = $user->getStoreId();

        if (! $storeId) {
            return null;
        }

        $session = $this->sessionRepository->findOpenSession(
            storeId: $storeId,
            userId: $user->id,
            deviceId: $deviceId,
        );

        return $session?->load('activeCart');
    }

    /**
     * Buka sesi kasir baru.
     *
     * @param  array{opening_cash: float|int, device_id: string}  $data
     *
     * @throws PosSessionAlreadyActiveException
     * @throws HttpException
     */
    public function openSession(User $user, array $data): PosSession
    {
        $storeId = $user->getStoreId();

        if (! $storeId) {
            throw new HttpException(403, 'Toko aktif tidak ditemukan untuk akun ini.');
        }

        return DB::transaction(function () use ($user, $data, $storeId) {
            // Cek apakah sudah ada session open untuk kombinasi ini (anti race-condition)
            if ($this->sessionRepository->hasOpenSession($storeId, $user->id, $data['device_id'])) {
                throw new PosSessionAlreadyActiveException;
            }

            // Buat PosSession
            $session = $this->sessionRepository->create([
                'store_id' => $storeId,
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
                'status' => 'open',
                'opening_cash' => $data['opening_cash'],
                'expected_cash' => $data['opening_cash'],
                'opened_at' => now(),
            ]);

            // Buat cart aktif untuk session ini
            $cart = $this->cartRepository->createActiveCartForSession($session);

            // Audit log
            $this->createAuditLog(
                storeId: $storeId,
                userId: $user->id,
                action: 'open_session',
                auditable: $session,
                oldValues: null,
                newValues: [
                    'status' => 'open',
                    'opening_cash' => $session->opening_cash,
                    'device_id' => $session->device_id,
                    'opened_at' => $session->opened_at?->toIso8601String(),
                ],
            );

            $session->setRelation('activeCart', $cart);

            return $session;
        });
    }

    /**
     * Tutup sesi kasir.
     *
     * @param  array{closing_cash: float|int, notes?: string|null}  $data
     *
     * @throws PosSessionAlreadyClosedException
     * @throws HttpException
     */
    public function closeSession(User $user, PosSession $session, array $data): PosSession
    {
        $storeId = $user->getStoreId();

        return DB::transaction(function () use ($user, $session, $data, $storeId) {
            // Lock for update agar aman dari race condition
            $session = $this->sessionRepository->lockById($session->id);

            // Validasi store scope
            if ((int) $session->store_id !== (int) $storeId) {
                throw new HttpException(403, 'Sesi kasir tidak ditemukan di toko Anda.');
            }

            // Validasi status
            if ($session->status !== 'open') {
                throw new PosSessionAlreadyClosedException;
            }

            // Validasi ownership: kasir hanya boleh tutup session miliknya sendiri
            $roleName = $user->role?->name ?? '';
            if ($roleName === 'kasir' && (int) $session->user_id !== (int) $user->id) {
                throw new HttpException(403, 'Anda tidak berhak menutup sesi kasir orang lain.');
            }

            $closingCash = (float) $data['closing_cash'];
            $expectedCash = $this->calculateExpectedCash($session);
            $cashDifference = $closingCash - $expectedCash;

            $oldValues = [
                'status' => $session->status,
                'opening_cash' => (float) $session->opening_cash,
                'expected_cash' => (float) $session->expected_cash,
            ];

            $session->update([
                'status' => 'closed',
                'closing_cash' => $closingCash,
                'expected_cash' => $expectedCash,
                'cash_difference' => $cashDifference,
                'closed_at' => now(),
            ]);

            // Audit log
            $this->createAuditLog(
                storeId: $storeId,
                userId: $user->id,
                action: 'close_session',
                auditable: $session,
                oldValues: $oldValues,
                newValues: [
                    'status' => 'closed',
                    'closing_cash' => $closingCash,
                    'expected_cash' => $expectedCash,
                    'cash_difference' => $cashDifference,
                    'closed_at' => $session->closed_at?->toIso8601String(),
                    'notes' => $data['notes'] ?? null,
                ],
            );

            return $session->fresh();
        });
    }

    /**
     * Hitung expected cash berdasarkan opening_cash + total transaksi cash.
     *
     * TODO: Ketika pos_payments dan pos_transactions tersedia, tambahkan:
     *   - Total cash payment dari transaksi berstatus `completed` dalam sesi ini.
     *   - Kurangi refund/void cash jika fitur void sudah tersedia.
     */
    public function calculateExpectedCash(PosSession $session): float
    {
        // Tahap awal: return opening_cash saja.
        // Nanti diganti dengan:
        // $cashPayments = $session->transactions()
        //     ->where('status', 'completed')
        //     ->whereHas('payments', fn ($q) => $q->where('method', 'cash'))
        //     ->with('payments')
        //     ->get()
        //     ->sum(fn ($trx) => $trx->payments->where('method', 'cash')->sum('amount'));
        // return (float) $session->opening_cash + $cashPayments - $voidedCash;

        return (float) $session->opening_cash;
    }

    /**
     * Simpan entri audit log ke tabel pos_audit_logs.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function createAuditLog(
        int $storeId,
        int $userId,
        string $action,
        PosSession $auditable,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        $request = app(Request::class);

        PosAuditLog::create([
            'store_id' => $storeId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => PosSession::class,
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
