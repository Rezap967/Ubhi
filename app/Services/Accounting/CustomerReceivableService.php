<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\CustomerReceivablePayment;
use App\Repositories\Accounting\CustomerReceivableRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerReceivableService
{
    public function __construct(
        protected CustomerReceivableRepository $repository,
        protected CustomerReceivableNumberService $numberService,
        protected CustomerReceivablePaymentNumberService $paymentNumberService,
        protected CustomerReceivableJournalService $journalService,
        protected AccountingPeriodService $periodService,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(int $storeId, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateForStore($storeId, $filters);
    }

    public function find(int $storeId, CustomerReceivable $receivable): CustomerReceivable
    {
        $this->validateBelongsToStore($storeId, $receivable);

        return $this->repository->findForStoreOrFail($storeId, $receivable->id);
    }

    /** @param array<string, mixed> $payload */
    public function create(int $storeId, array $payload): CustomerReceivable
    {
        $this->validateAccounts($storeId, $payload);
        $this->ensurePeriodIsOpen($storeId, $payload['receivable_date']);

        return DB::transaction(function () use ($storeId, $payload): CustomerReceivable {
            $amount = (float) $payload['original_amount'];
            $receivable = $this->repository->create([
                ...$payload,
                'store_id' => $storeId,
                'receivable_no' => $this->numberService->generate($storeId, Carbon::parse($payload['receivable_date'])),
                'source_type' => $payload['source_type'] ?? 'manual_receivable',
                'paid_amount' => 0,
                'outstanding_amount' => $amount,
                'status' => Carbon::parse($payload['due_date'])->lt(today()) ? 'overdue' : 'open',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $journal = $this->journalService->createRecognitionJournal($receivable);
            $receivable->update(['journal_entry_id' => $journal->id]);

            return $this->repository->findForStoreOrFail($storeId, $receivable->id);
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(int $storeId, CustomerReceivable $receivable, array $payload): CustomerReceivable
    {
        $this->validateBelongsToStore($storeId, $receivable);
        abort_if($receivable->status === 'void' || (float) $receivable->paid_amount > 0, 409, 'Piutang yang sudah dibayar atau dibatalkan tidak dapat diedit.');
        $this->validateAccounts($storeId, $payload);
        $this->ensurePeriodIsOpen($storeId, $payload['receivable_date']);

        return DB::transaction(function () use ($storeId, $receivable, $payload): CustomerReceivable {
            $updated = $this->repository->update($receivable, [
                ...$payload,
                'outstanding_amount' => $payload['original_amount'],
                'updated_by' => Auth::id(),
            ]);
            $this->recalculateStatus($updated);
            $this->journalService->updateRecognitionJournal($updated);

            return $this->repository->findForStoreOrFail($storeId, $updated->id);
        });
    }

    public function delete(int $storeId, CustomerReceivable $receivable): void
    {
        $this->validateBelongsToStore($storeId, $receivable);
        abort_if($receivable->journal_entry_id !== null || (float) $receivable->paid_amount > 0 || $receivable->payments()->exists(), 409, 'Piutang posted atau yang sudah memiliki pembayaran tidak dapat dihapus; gunakan void.');
        $receivable->delete();
    }

    /** @param array<string, mixed> $payload */
    public function addPayment(int $storeId, CustomerReceivable $receivable, array $payload): CustomerReceivablePayment
    {
        $this->validateBelongsToStore($storeId, $receivable);
        $this->ensurePeriodIsOpen($storeId, $payload['payment_date']);
        $this->validatePaymentAccount($storeId, $payload['cash_or_bank_account_id']);

        return DB::transaction(function () use ($storeId, $receivable, $payload): CustomerReceivablePayment {
            $locked = CustomerReceivable::query()->forStore($storeId)->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'void' || $locked->status === 'paid', 409, 'Piutang ini tidak dapat menerima pembayaran.');
            $amount = (float) $payload['amount'];
            abort_if($amount > (float) $locked->outstanding_amount, 422, 'Jumlah penerimaan melebihi sisa piutang.');
            $payment = CustomerReceivablePayment::query()->create([
                ...$payload,
                'store_id' => $storeId,
                'customer_receivable_id' => $locked->id,
                'payment_no' => $this->paymentNumberService->generate($storeId, Carbon::parse($payload['payment_date'])),
                'created_by' => Auth::id(),
            ]);
            $journal = $this->journalService->createPaymentJournal($payment->load('receivable'));
            $payment->update(['journal_entry_id' => $journal->id]);
            $locked->update([
                'paid_amount' => (float) $locked->paid_amount + $amount,
                'outstanding_amount' => max(0, (float) $locked->outstanding_amount - $amount),
            ]);
            $this->recalculateStatus($locked);

            return $payment->fresh(['journalEntry', 'cashOrBankAccount']);
        });
    }

    public function void(int $storeId, CustomerReceivable $receivable, string $reason): CustomerReceivable
    {
        $this->validateBelongsToStore($storeId, $receivable);
        $this->ensurePeriodIsOpen($storeId, today()->toDateString());

        return DB::transaction(function () use ($storeId, $receivable, $reason): CustomerReceivable {
            $locked = CustomerReceivable::query()->forStore($storeId)->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'void', 409, 'Piutang sudah dibatalkan.');
            abort_if((float) $locked->paid_amount > 0, 409, 'Piutang yang sudah memiliki pembayaran tidak dapat dibatalkan.');
            if ($locked->journal_entry_id !== null) {
                $this->journalService->createVoidReversalJournal($locked, $reason);
            }
            $locked->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => Auth::id(), 'void_reason' => $reason, 'updated_by' => Auth::id()]);

            return $locked->fresh(['payments', 'journalEntry']);
        });
    }

    public function recalculateStatus(CustomerReceivable $receivable): CustomerReceivable
    {
        $paid = (float) $receivable->paid_amount;
        $outstanding = (float) $receivable->outstanding_amount;
        $status = $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partial' : ($receivable->due_date->lt(today()) ? 'overdue' : 'open'));
        $receivable->update(['status' => $status]);

        return $receivable;
    }

    public function validateBelongsToStore(int $storeId, CustomerReceivable $receivable): void
    {
        if ((int) $receivable->store_id !== $storeId) {
            throw (new ModelNotFoundException)->setModel(CustomerReceivable::class, [$receivable->id]);
        }
    }

    /** @param array<string, mixed> $payload */
    public function validateAccounts(int $storeId, array $payload): void
    {
        $accounts = Account::query()->forStore($storeId)->active()->whereIn('id', [$payload['receivable_account_id'], $payload['revenue_account_id']])->get()->keyBy('id');
        abort_unless($accounts->count() === 2, 422, 'Akun piutang tidak aktif atau bukan milik toko aktif.');
        abort_unless($accounts[$payload['receivable_account_id']]->type === 'asset', 422, 'Akun piutang harus bertipe asset.');
        abort_unless($accounts[$payload['revenue_account_id']]->type === 'revenue', 422, 'Akun pendapatan harus bertipe revenue.');
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
