<?php

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\SupplierPayable;
use App\Models\Accounting\SupplierPayablePayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SupplierPayableJournalService
{
    public function __construct(protected JournalNumberService $journalNumberService) {}

    public function createRecognitionJournal(SupplierPayable $payable): JournalEntry
    {
        $amount = $payable->original_amount;
        $journal = $this->createJournal($payable->store_id, $payable->payable_date, $payable->payable_no, 'supplier_payable', $payable->id, 'Pengakuan hutang pemasok '.$payable->supplier_name, $amount, $amount);
        $journal->lines()->createMany([
            ['store_id' => $payable->store_id, 'account_id' => $payable->expense_or_inventory_account_id, 'description' => 'Pengakuan hutang '.$payable->payable_no, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
            ['store_id' => $payable->store_id, 'account_id' => $payable->liability_account_id, 'description' => 'Pengakuan hutang '.$payable->payable_no, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
        ]);

        return $journal->fresh(['lines.account']);
    }

    public function createPaymentJournal(SupplierPayablePayment $payment): JournalEntry
    {
        $payable = $payment->payable;
        $amount = $payment->amount;
        $journal = $this->createJournal($payment->store_id, $payment->payment_date, $payment->payment_no, 'supplier_payable_payment', $payment->id, 'Pembayaran hutang '.$payable->payable_no, $amount, $amount);
        $journal->lines()->createMany([
            ['store_id' => $payment->store_id, 'account_id' => $payable->liability_account_id, 'description' => 'Pembayaran hutang '.$payable->payable_no, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
            ['store_id' => $payment->store_id, 'account_id' => $payment->cash_or_bank_account_id, 'description' => 'Pembayaran hutang '.$payable->payable_no, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
        ]);

        return $journal->fresh(['lines.account']);
    }

    public function createVoidReversalJournal(SupplierPayable $payable, string $reason): JournalEntry
    {
        abort_unless($payable->journal_entry_id !== null, 409, 'Hutang tidak memiliki jurnal posted yang dapat dibatalkan.');
        $original = $payable->journalEntry()->with('lines')->firstOrFail();
        $journal = $this->createJournal($payable->store_id, today(), $payable->payable_no, 'supplier_payable_void', $payable->id, 'Void hutang '.$payable->payable_no.': '.$reason, $original->total_credit, $original->total_debit);
        foreach ($original->lines as $line) {
            $journal->lines()->create([
                'store_id' => $payable->store_id, 'account_id' => $line->account_id,
                'description' => 'Void '.$line->description, 'debit' => $line->credit,
                'credit' => $line->debit, 'line_order' => $line->line_order,
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
