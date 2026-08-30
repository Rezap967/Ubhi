<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\TaxRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TaxJournalService
{
    public function __construct(protected JournalNumberService $numbers) {}

    public function createPaidJournal(TaxRecord $record): JournalEntry
    {
        abort_unless($record->tax_account_id && $record->counter_account_id, 422, 'Akun pajak dan akun lawan wajib diisi.');
        abort_unless($record->tax_account_id !== $record->counter_account_id, 422, 'Akun pajak dan akun lawan tidak boleh sama.');
        abort_unless((float) $record->tax_amount > 0, 422, 'Nominal pajak harus lebih besar dari nol.');
        $a = Account::query()->forStore($record->store_id)->active()->whereIn('id', [$record->tax_account_id, $record->counter_account_id])->get()->keyBy('id');
        $tax = $a->get($record->tax_account_id);
        $counter = $a->get($record->counter_account_id);
        abort_unless($tax && $counter, 422, 'Akun pajak tidak valid.');
        $debit = $tax->normal_balance === 'debit' ? $tax->id : $counter->id;
        $credit = $tax->normal_balance === 'debit' ? $counter->id : $tax->id;
        $j = $this->journal($record, 'tax_record', $record->tax_date);
        $j->lines()->createMany([['store_id' => $record->store_id, 'account_id' => $debit, 'description' => 'Pembayaran pajak '.$record->transaction_no, 'debit' => $record->tax_amount, 'credit' => 0, 'line_order' => 0], ['store_id' => $record->store_id, 'account_id' => $credit, 'description' => 'Pembayaran pajak '.$record->transaction_no, 'debit' => 0, 'credit' => $record->tax_amount, 'line_order' => 1]]);

        return $j->fresh(['lines.account']);
    }

    public function createCancelReversalJournal(TaxRecord $record, string $reason): JournalEntry
    {
        abort_unless($record->journal_entry_id !== null, 409, 'Catatan pajak tidak memiliki jurnal.');
        $original = $record->journalEntry()->with('lines')->firstOrFail();
        $j = $this->journal($record, 'tax_record_cancel', today(), $reason, $original->total_credit, $original->total_debit);
        foreach ($original->lines as $line) {
            $j->lines()->create(['store_id' => $record->store_id, 'account_id' => $line->account_id, 'description' => 'Pembatalan '.$line->description, 'debit' => $line->credit, 'credit' => $line->debit, 'line_order' => $line->line_order]);
        }

        return $j->fresh(['lines.account']);
    }

    protected function journal(TaxRecord $record, string $source, string|Carbon $date, ?string $reason = null, string|float|null $debit = null, string|float|null $credit = null): JournalEntry
    {
        $amount = $record->tax_amount;

        return JournalEntry::query()->create(['store_id' => $record->store_id, 'journal_no' => $this->numbers->generate($record->store_id, Carbon::parse($date)), 'journal_date' => $date, 'description' => ($source === 'tax_record_cancel' ? 'Pembatalan ' : 'Pembayaran ').'pajak '.$record->transaction_no.($reason ? ': '.$reason : ''), 'status' => 'posted', 'source_type' => $source, 'source_id' => $record->id, 'reference_no' => $record->transaction_no, 'total_debit' => $debit ?? $amount, 'total_credit' => $credit ?? $amount, 'posted_at' => now(), 'posted_by' => Auth::id(), 'created_by' => Auth::id(), 'updated_by' => Auth::id()]);
    }
}
