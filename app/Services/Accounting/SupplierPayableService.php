<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\SupplierPayable;
use App\Models\Accounting\SupplierPayablePayment;
use App\Repositories\Accounting\SupplierPayableRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPayableService
{
    public function __construct(
        protected SupplierPayableRepository $repository,
        protected SupplierPayableNumberService $numberService,
        protected SupplierPayablePaymentNumberService $paymentNumberService,
        protected SupplierPayableJournalService $journalService,
        protected AccountingPeriodService $periodService,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(int $storeId, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateForStore($storeId, $filters);
    }

    public function find(int $storeId, SupplierPayable $payable): SupplierPayable
    {
        $this->validateBelongsToStore($storeId, $payable);

        return $this->repository->findForStoreOrFail($storeId, $payable->id);
    }

    /** @param array<string, mixed> $payload */
    public function create(int $storeId, array $payload): SupplierPayable
    {
        $this->validateAccounts($storeId, $payload);
        $this->ensurePeriodIsOpen($storeId, $payload['payable_date']);

        return DB::transaction(function () use ($storeId, $payload): SupplierPayable {
            $amount = (float) $payload['original_amount'];
            $payable = $this->repository->create([
                ...$payload,
                'store_id' => $storeId,
                'payable_no' => $this->numberService->generate($storeId, Carbon::parse($payload['payable_date'])),
                'source_type' => $payload['source_type'] ?? 'manual_payable',
                'paid_amount' => 0,
                'outstanding_amount' => $amount,
                'status' => Carbon::parse($payload['due_date'])->lt(today()) ? 'overdue' : 'open',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $journal = $this->journalService->createRecognitionJournal($payable);
            $payable->update(['journal_entry_id' => $journal->id]);

            return $this->repository->findForStoreOrFail($storeId, $payable->id);
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(int $storeId, SupplierPayable $payable, array $payload): SupplierPayable
    {
        $this->validateBelongsToStore($storeId, $payable);
        abort_if($payable->status === 'void' || (float) $payable->paid_amount > 0, 409, 'Hutang yang sudah dibayar atau dibatalkan tidak dapat diedit.');
        abort_if($payable->journal_entry_id !== null, 409, 'Hutang posted tidak dapat diedit; gunakan pembatalan lalu buat transaksi baru.');
        $this->validateAccounts($storeId, $payload);
        $this->ensurePeriodIsOpen($storeId, $payload['payable_date']);

        return DB::transaction(fn (): SupplierPayable => $this->repository->update($payable, [...$payload, 'outstanding_amount' => $payload['original_amount'], 'updated_by' => Auth::id()]));
    }

    public function delete(int $storeId, SupplierPayable $payable): void
    {
        $this->validateBelongsToStore($storeId, $payable);
        abort_if($payable->journal_entry_id !== null || (float) $payable->paid_amount > 0, 409, 'Hutang posted atau yang sudah memiliki pembayaran tidak dapat dihapus; gunakan void.');
        $payable->delete();
    }

    /** @param array<string, mixed> $payload */
    public function addPayment(int $storeId, SupplierPayable $payable, array $payload): SupplierPayablePayment
    {
        $this->validateBelongsToStore($storeId, $payable);
        $this->ensurePeriodIsOpen($storeId, $payload['payment_date']);
        $this->validatePaymentAccount($storeId, $payload['cash_or_bank_account_id']);

        return DB::transaction(function () use ($storeId, $payable, $payload): SupplierPayablePayment {
            $locked = SupplierPayable::query()->forStore($storeId)->whereKey($payable->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'void' || $locked->status === 'paid', 409, 'Hutang ini tidak dapat menerima pembayaran.');
            $amount = (float) $payload['amount'];
            abort_if($amount > (float) $locked->outstanding_amount, 422, 'Jumlah pembayaran melebihi sisa hutang.');
            $locked->load('liabilityAccount');
            $payment = SupplierPayablePayment::query()->create([
                ...$payload,
                'store_id' => $storeId,
                'supplier_payable_id' => $locked->id,
                'payment_no' => $this->paymentNumberService->generate($storeId, Carbon::parse($payload['payment_date'])),
                'created_by' => Auth::id(),
            ]);
            $journal = $this->journalService->createPaymentJournal($payment->load('payable.liabilityAccount'));
            $payment->update(['journal_entry_id' => $journal->id]);
            $locked->update([
                'paid_amount' => (float) $locked->paid_amount + $amount,
                'outstanding_amount' => max(0, (float) $locked->outstanding_amount - $amount),
            ]);
            $this->recalculateStatus($locked);

            return $payment->fresh(['journalEntry', 'cashOrBankAccount']);
        });
    }

    public function void(int $storeId, SupplierPayable $payable, string $reason): SupplierPayable
    {
        $this->validateBelongsToStore($storeId, $payable);
        $this->ensurePeriodIsOpen($storeId, today()->toDateString());

        return DB::transaction(function () use ($storeId, $payable, $reason): SupplierPayable {
            $locked = SupplierPayable::query()->forStore($storeId)->whereKey($payable->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'void', 409, 'Hutang sudah dibatalkan.');
            abort_if((float) $locked->paid_amount > 0, 409, 'Hutang yang sudah memiliki pembayaran tidak dapat dibatalkan.');
            if ($locked->journal_entry_id !== null) {
                $this->journalService->createVoidReversalJournal($locked, $reason);
            }
            $locked->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => Auth::id(), 'void_reason' => $reason, 'updated_by' => Auth::id()]);

            return $locked->fresh(['payments', 'journalEntry']);
        });
    }

    public function recalculateStatus(SupplierPayable $payable): SupplierPayable
    {
        $paid = (float) $payable->paid_amount;
        $outstanding = (float) $payable->outstanding_amount;
        $status = $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partial' : ($payable->due_date->lt(today()) ? 'overdue' : 'open'));
        $payable->update(['status' => $status]);

        return $payable;
    }

    public function validateBelongsToStore(int $storeId, SupplierPayable $payable): void
    {
        if ((int) $payable->store_id !== $storeId) {
            throw (new ModelNotFoundException)->setModel(SupplierPayable::class, [$payable->id]);
        }
    }

    /** @param array<string, mixed> $payload */
    public function validateAccounts(int $storeId, array $payload): void
    {
        $accounts = Account::query()->forStore($storeId)->active()->whereIn('id', [$payload['expense_or_inventory_account_id'], $payload['liability_account_id']])->get()->keyBy('id');
        abort_unless($accounts->count() === 2, 422, 'Akun hutang tidak aktif atau bukan milik toko aktif.');
        abort_unless(in_array($accounts[$payload['expense_or_inventory_account_id']]->type, ['expense', 'asset'], true), 422, 'Akun beban/persediaan harus bertipe expense atau asset.');
        abort_unless($accounts[$payload['liability_account_id']]->type === 'liability', 422, 'Akun kewajiban harus bertipe liability.');
    }

    public function ensurePeriodIsOpen(int $storeId, string|Carbon $date): void
    {
        abort_if($this->periodService->isPeriodLocked($storeId, $date), 409, 'Periode akuntansi sudah terkunci.');
    }

    protected function validatePaymentAccount(int $storeId, string $accountId): void
    {
        $account = Account::query()->forStore($storeId)->active()->whereKey($accountId)->first();
        abort_unless($account !== null && $account->type === 'asset', 422, 'Akun kas/bank harus aktif, bertipe asset, dan milik toko aktif.');
    }
}
