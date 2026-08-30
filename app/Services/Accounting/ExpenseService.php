<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Repositories\Accounting\ExpenseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected ExpenseRepository $repository,
        protected ExpenseNumberService $numberService,
        protected ExpenseJournalService $journalService,
        protected AccountingPeriodService $periodService,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(int $storeId, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateForStore($storeId, $filters);
    }

    public function find(int $storeId, string $id): Expense
    {
        return $this->repository->findForStoreOrFail($storeId, $id);
    }

    /** @param array<string, mixed> $payload */
    public function create(int $storeId, array $payload): Expense
    {
        return DB::transaction(function () use ($storeId, $payload): Expense {
            $this->validateAccounts($storeId, $payload);
            $this->ensurePeriodIsOpen($storeId, $payload['expense_date']);
            $amount = round((float) $payload['amount'], 2);
            $taxAmount = round((float) ($payload['tax_amount'] ?? 0), 2);
            $expense = $this->repository->create([
                'store_id' => $storeId,
                'expense_no' => $this->numberService->generate($storeId, Carbon::parse($payload['expense_date'])),
                'expense_date' => $payload['expense_date'],
                'description' => $payload['description'],
                'category_id' => $payload['category_id'] ?? null,
                'expense_account_id' => $payload['expense_account_id'],
                'cash_or_bank_account_id' => $payload['cash_or_bank_account_id'],
                'amount' => $amount,
                'tax_rate_id' => $payload['tax_rate_id'] ?? null,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'payment_method' => $payload['payment_method'],
                'reference_no' => $payload['reference_no'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? 'draft',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($expense->status === 'posted') {
                $journal = $this->journalService->createPostedJournalForExpense($expense);
                $expense->update(['journal_entry_id' => $journal->id]);
            }

            return $this->repository->findForStoreOrFail($storeId, $expense->id);
        });
    }

    /** @param array<string, mixed> $payload */
    public function updateDraft(int $storeId, Expense $expense, array $payload): Expense
    {
        $this->validateBelongsToStore($storeId, $expense);
        abort_unless($expense->status === 'draft', 409, 'Pengeluaran posted atau void tidak boleh diubah.');

        return DB::transaction(function () use ($storeId, $expense, $payload): Expense {
            $this->validateAccounts($storeId, $payload);
            $this->ensurePeriodIsOpen($storeId, $payload['expense_date']);
            $amount = round((float) $payload['amount'], 2);
            $taxAmount = round((float) ($payload['tax_amount'] ?? 0), 2);
            $data = [
                'expense_date' => $payload['expense_date'],
                'description' => $payload['description'],
                'category_id' => $payload['category_id'] ?? null,
                'expense_account_id' => $payload['expense_account_id'],
                'cash_or_bank_account_id' => $payload['cash_or_bank_account_id'],
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'payment_method' => $payload['payment_method'],
                'reference_no' => $payload['reference_no'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => Auth::id(),
            ];
            $this->repository->update($expense, $data);

            if (($payload['status'] ?? 'draft') === 'posted') {
                $journal = $this->journalService->createPostedJournalForExpense($expense->fresh());
                $expense->update(['status' => 'posted', 'journal_entry_id' => $journal->id]);
            }

            return $this->repository->findForStoreOrFail($storeId, $expense->id);
        });
    }

    public function deleteDraft(int $storeId, Expense $expense): void
    {
        $this->validateBelongsToStore($storeId, $expense);
        abort_unless($expense->status === 'draft', 409, 'Pengeluaran posted atau void tidak boleh dihapus.');
        DB::transaction(fn () => $expense->delete());
    }

    public function void(int $storeId, Expense $expense, string $reason): Expense
    {
        $this->validateBelongsToStore($storeId, $expense);
        abort_unless($expense->status === 'posted', 409, 'Hanya pengeluaran posted yang dapat dibatalkan.');

        return DB::transaction(function () use ($storeId, $expense, $reason): Expense {
            $this->journalService->reverseJournalForExpense($expense, $reason);
            $expense->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
                'updated_by' => Auth::id(),
            ]);

            return $this->repository->findForStoreOrFail($storeId, $expense->id);
        });
    }

    public function validateBelongsToStore(int $storeId, Expense $expense): void
    {
        abort_unless((int) $expense->store_id === $storeId, 404);
    }

    /** @param array<string, mixed> $payload */
    public function validateAccounts(int $storeId, array $payload): void
    {
        $accounts = Account::query()->forStore($storeId)->active()->whereIn('id', [
            $payload['expense_account_id'],
            $payload['cash_or_bank_account_id'],
        ])->get()->keyBy('id');

        if (($accounts[$payload['expense_account_id']] ?? null)?->type !== 'expense'
            || ($accounts[$payload['cash_or_bank_account_id']] ?? null)?->type !== 'asset') {
            abort(422, 'Akun pengeluaran tidak valid atau bukan milik toko aktif.');
        }
    }

    public function ensurePeriodIsOpen(int $storeId, string $date): void
    {
        abort_if($this->periodService->isPeriodLocked($storeId, $date), 409, 'Periode pengeluaran sudah terkunci.');
    }

    /** @return Collection<int, ExpenseCategory> */
    public function activeCategories(int $storeId): Collection
    {
        return $this->repository->getActiveCategories($storeId);
    }
}
