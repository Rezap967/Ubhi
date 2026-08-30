<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\CustomerReceivablePayment;
use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CustomerReceivableJournalService
{
    public function __construct(protected JournalNumberService $journalNumberService) {}

    public function createRecognitionJournal(CustomerReceivable $receivable): JournalEntry
    {
        $amount = $receivable->original_amount;
        $journal = $this->createJournal($receivable->store_id, $receivable->receivable_date, $receivable->receivable_no, 'customer_receivable', $receivable->id, 'Pengakuan piutang pelanggan '.$receivable->customer_name, $amount, $amount);
        $journal->lines()->createMany([
            ['store_id' => $receivable->store_id, 'account_id' => $receivable->receivable_account_id, 'description' => 'Pengakuan piutang '.$receivable->receivable_no, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
            ['store_id' => $receivable->store_id, 'account_id' => $receivable->revenue_account_id, 'description' => 'Pengakuan piutang '.$receivable->receivable_no, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
        ]);

        return $journal->fresh(['lines.account']);
    }

    public function createPaymentJournal(CustomerReceivablePayment $payment): JournalEntry
    {
        $receivable = $payment->receivable;
        $amount = $payment->amount;
        $journal = $this->createJournal($payment->store_id, $payment->payment_date, $payment->payment_no, 'customer_receivable_payment', $payment->id, 'Penerimaan piutang '.$receivable->receivable_no, $amount, $amount);
        $journal->lines()->createMany([
            ['store_id' => $payment->store_id, 'account_id' => $payment->cash_or_bank_account_id, 'description' => 'Penerimaan piutang '.$receivable->receivable_no, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
            ['store_id' => $payment->store_id, 'account_id' => $receivable->receivable_account_id, 'description' => 'Penerimaan piutang '.$receivable->receivable_no, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
        ]);

        return $journal->fresh(['lines.account']);
    }

    public function updateRecognitionJournal(CustomerReceivable $receivable): void
    {
        if ($receivable->journal_entry_id === null) {
            return;
        }

        $journal = $receivable->journalEntry()->with('lines')->firstOrFail();
        $amount = $receivable->original_amount;
        $journal->update([
            'journal_date' => $receivable->receivable_date,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'updated_by' => Auth::id(),
        ]);
        $lines = $journal->lines->sortBy('line_order')->values();
        $lines->get(0)?->update(['account_id' => $receivable->receivable_account_id, 'debit' => $amount, 'credit' => 0]);
        $lines->get(1)?->update(['account_id' => $receivable->revenue_account_id, 'debit' => 0, 'credit' => $amount]);
    }

    public function createVoidReversalJournal(CustomerReceivable $receivable, string $reason): JournalEntry
    {
        abort_unless($receivable->journal_entry_id !== null, 409, 'Piutang tidak memiliki jurnal posted yang dapat dibatalkan.');
        $original = $receivable->journalEntry()->with('lines')->firstOrFail();
        $journal = $this->createJournal($receivable->store_id, today(), $receivable->receivable_no, 'customer_receivable_void', $receivable->id, 'Void piutang '.$receivable->receivable_no.': '.$reason, $original->total_credit, $original->total_debit);
        foreach ($original->lines as $line) {
            $journal->lines()->create([
                'store_id' => $receivable->store_id,
                'account_id' => $line->account_id,
                'description' => 'Void '.$line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'line_order' => $line->line_order,
            ]);
        }

        return $journal->fresh(['lines.account']);
    }

    protected function createJournal(int $storeId, string|Carbon $date, string $reference, string $sourceType, string $sourceId, string $description, string|float $debit, string|float $credit): JournalEntry
    {
        $userId = Auth::id();

        return JournalEntry::query()->create([
            'store_id' => $storeId,
            'journal_no' => $this->journalNumberService->generate($storeId, Carbon::parse($date)),
            'journal_date' => $date,
            'description' => $description,
            'status' => 'posted',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_no' => $reference,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'posted_at' => now(),
            'posted_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
