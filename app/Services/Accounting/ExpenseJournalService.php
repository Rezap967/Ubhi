<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ExpenseJournalService
{
    public function __construct(protected JournalNumberService $journalNumberService) {}

    public function createPostedJournalForExpense(Expense $expense): JournalEntry
    {
        $userId = Auth::id();
        $journal = JournalEntry::query()->create([
            'store_id' => $expense->store_id,
            'journal_no' => $this->journalNumberService->generate($expense->store_id, Carbon::parse($expense->expense_date)),
            'journal_date' => $expense->expense_date,
            'description' => 'Pengeluaran: '.$expense->description,
            'status' => 'posted',
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'reference_no' => $expense->expense_no,
            'total_debit' => $expense->total_amount,
            'total_credit' => $expense->total_amount,
            'posted_at' => now(),
            'posted_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $journal->lines()->createMany([
            [
                'store_id' => $expense->store_id,
                'account_id' => $expense->expense_account_id,
                'description' => $expense->description,
                'debit' => $expense->total_amount,
                'credit' => 0,
                'line_order' => 0,
            ],
            [
                'store_id' => $expense->store_id,
                'account_id' => $expense->cash_or_bank_account_id,
                'description' => $expense->description,
                'debit' => 0,
                'credit' => $expense->total_amount,
                'line_order' => 1,
            ],
        ]);

        return $journal->fresh(['lines.account']);
    }

    public function reverseJournalForExpense(Expense $expense, string $reason): JournalEntry
    {
        abort_unless($expense->status === 'posted' && $expense->journal_entry_id !== null, 409, 'Pengeluaran tidak memiliki jurnal posted yang dapat dibatalkan.');
        $original = $expense->journalEntry()->with('lines')->firstOrFail();
        $userId = Auth::id();

        $reversal = JournalEntry::query()->create([
            'store_id' => $expense->store_id,
            'journal_no' => $this->journalNumberService->generate($expense->store_id, Carbon::parse($expense->expense_date)),
            'journal_date' => $expense->expense_date,
            'description' => 'Void pengeluaran '.$expense->expense_no.': '.$reason,
            'status' => 'posted',
            'source_type' => 'expense_void',
            'source_id' => $expense->id,
            'reference_no' => $expense->expense_no,
            'total_debit' => $original->total_credit,
            'total_credit' => $original->total_debit,
            'posted_at' => now(),
            'posted_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach ($original->lines as $line) {
            $reversal->lines()->create([
                'store_id' => $expense->store_id,
                'account_id' => $line->account_id,
                'description' => 'Void '.$line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'line_order' => $line->line_order,
            ]);
        }

        return $reversal->fresh(['lines.account']);
    }
}
