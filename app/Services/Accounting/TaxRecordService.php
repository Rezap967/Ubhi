<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\TaxRate;
use App\Models\Accounting\TaxRecord;
use App\Repositories\Accounting\TaxRecordRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxRecordService
{
    public function __construct(protected TaxRecordRepository $repo, protected TaxRecordNumberService $numbers, protected TaxJournalService $journals, protected AccountingPeriodService $periods) {}

    public function list(int $storeId, array $filters): LengthAwarePaginator
    {
        return $this->repo->paginateForStore($storeId, $filters);
    }

    public function find(int $storeId, string $id): TaxRecord
    {
        return $this->repo->findForStoreOrFail($storeId, $id);
    }

    public function create(int $storeId, array $payload): TaxRecord
    {
        return DB::transaction(function () use ($storeId, $payload) {
            $data = $this->prepare($storeId, $payload);
            $this->ensurePeriodIsOpen($storeId, $data['tax_date']);

            if (! empty($data['source_type']) && ! empty($data['source_id'])) {
                $existing = TaxRecord::query()
                    ->forStore($storeId)
                    ->where('source_type', $data['source_type'])
                    ->where('source_id', $data['source_id'])
                    ->first();

                if ($existing) {
                    return $this->repo->findForStoreOrFail($storeId, $existing->id);
                }
            }

            $r = $this->repo->create([...$data, 'store_id' => $storeId, 'transaction_no' => $payload['transaction_no'] ?? $this->numbers->generate($storeId, Carbon::parse($data['tax_date'])), 'source_type' => $payload['source_type'] ?? 'manual_tax', 'status' => $payload['status'] ?? 'pending', 'created_by' => Auth::id(), 'updated_by' => Auth::id()]);
            if ($r->status === 'paid') {
                $this->validateAccountsForPaid($storeId, $data);
                $j = $this->journals->createPaidJournal($r);
                $r->update(['journal_entry_id' => $j->id, 'paid_at' => now()]);
            }

            return $this->repo->findForStoreOrFail($storeId, $r->id);
        });
    }

    public function update(int $storeId, TaxRecord $r, array $payload): TaxRecord
    {
        $this->validateBelongsToStore($storeId, $r);
        abort_unless($r->status === 'pending' && $r->journal_entry_id === null, 409, 'Catatan pajak sudah diproses.');

        return DB::transaction(function () use ($storeId, $r, $payload): TaxRecord {
            $data = $this->prepare($storeId, $payload);
            $this->ensurePeriodIsOpen($storeId, $data['tax_date']);

            return $this->repo->update($r, [...$data, 'updated_by' => Auth::id()]);
        });
    }

    public function delete(int $storeId, TaxRecord $r): void
    {
        $this->validateBelongsToStore($storeId, $r);
        abort_unless($r->status === 'pending' && $r->journal_entry_id === null, 409, 'Hanya catatan pajak pending tanpa jurnal yang dapat dihapus.');
        $r->delete();
    }

    public function pay(int $storeId, TaxRecord $r, array $payload): TaxRecord
    {
        $this->validateBelongsToStore($storeId, $r);

        return DB::transaction(function () use ($storeId, $r, $payload) {
            $x = TaxRecord::query()->forStore($storeId)->whereKey($r->id)->lockForUpdate()->firstOrFail();
            abort_unless($x->status === 'pending', 409, 'Catatan pajak sudah diproses.');
            $this->ensurePeriodIsOpen($storeId, $payload['paid_at'] ?? today()->toDateString());
            $this->validateAccountsForPaid($storeId, [...$payload, 'tax_amount' => $x->tax_amount]);
            $x->update(['tax_account_id' => $payload['tax_account_id'], 'counter_account_id' => $payload['counter_account_id']]);
            $j = $this->journals->createPaidJournal($x->fresh());
            $x->update(['status' => 'paid', 'paid_at' => $payload['paid_at'] ?? now(), 'journal_entry_id' => $j->id, 'updated_by' => Auth::id()]);

            return $this->repo->findForStoreOrFail($storeId, $x->id);
        });
    }

    public function cancel(int $storeId, TaxRecord $r, string $reason): TaxRecord
    {
        $this->validateBelongsToStore($storeId, $r);

        return DB::transaction(function () use ($storeId, $r, $reason) {
            $x = TaxRecord::query()->forStore($storeId)->whereKey($r->id)->lockForUpdate()->firstOrFail();
            abort_unless($x->status !== 'cancelled', 409, 'Catatan pajak sudah dibatalkan.');
            $this->ensurePeriodIsOpen($storeId, today()->toDateString());
            if ($x->status === 'paid' && $x->journal_entry_id) {
                $this->journals->createCancelReversalJournal($x, $reason);
            }

            $x->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => Auth::id(), 'cancel_reason' => $reason]);

            return $this->repo->findForStoreOrFail($storeId, $x->id);
        });
    }

    public function validateBelongsToStore(int $storeId, TaxRecord $r): void
    {
        abort_unless((int) $r->store_id === $storeId, 404);
    }

    public function validateAccountsForPaid(int $storeId, array $p): void
    {
        abort_unless(
            ! empty($p['tax_account_id']) && ! empty($p['counter_account_id']),
            422,
            'Akun pajak dan akun lawan wajib diisi saat status dibayar.'
        );
        abort_unless(
            $p['tax_account_id'] !== $p['counter_account_id'],
            422,
            'Akun pajak dan akun lawan tidak boleh sama.'
        );
        abort_unless((float) ($p['tax_amount'] ?? 0) > 0, 422, 'Nominal pajak harus lebih besar dari nol.');

        $a = Account::query()->forStore($storeId)->active()->whereIn('id', [$p['tax_account_id'] ?? null, $p['counter_account_id'] ?? null])->count();
        abort_unless($a === 2, 422, 'Akun pajak dan lawan wajib aktif serta milik toko aktif.');
    }

    public function ensurePeriodIsOpen(int $storeId, string|Carbon $date): void
    {
        abort_if($this->periods->isPeriodLocked($storeId, $date), 409, 'Periode pajak sudah terkunci.');
    }

    protected function prepare(int $storeId, array $p): array
    {
        $rate = $p['tax_rate'] ?? 0;
        $name = $p['tax_name'] ?? null;
        if (! empty($p['tax_rate_id'])) {
            $tr = TaxRate::query()->forStore($storeId)->active()->whereKey($p['tax_rate_id'])->firstOrFail();
            $rate = $tr->rate;
            $name ??= $tr->name;
        }
        $base = round((float) $p['tax_base'], 2);
        $taxRate = round((float) $rate, 4);
        $taxAmount = array_key_exists('tax_amount', $p) && $p['tax_amount'] !== null
            ? round((float) $p['tax_amount'], 2)
            : round($base * $taxRate / 100, 2);

        return ['tax_date' => $p['tax_date'], 'tax_rate_id' => $p['tax_rate_id'] ?? null, 'tax_type' => $p['tax_type'], 'tax_name' => $name ?? $p['tax_type'], 'tax_base' => $base, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'tax_account_id' => $p['tax_account_id'] ?? null, 'counter_account_id' => $p['counter_account_id'] ?? null, 'source_type' => $p['source_type'] ?? null, 'source_id' => $p['source_id'] ?? null, 'reference_no' => $p['reference_no'] ?? null, 'notes' => $p['notes'] ?? null];
    }
}
