<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use App\Repositories\Accounting\JournalEntryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function __construct(
        protected JournalEntryRepository $repository,
        protected JournalNumberService $numberService,
        protected AccountingPeriodService $periodService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, JournalEntry>
     */
    public function list(int $storeId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($storeId, $filters, (int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, JournalEntry>
     */
    public function search(int $storeId, array $filters = [], int $limit = 20): Collection
    {
        return $this->repository->search($storeId, $filters, $limit);
    }

    public function find(int $storeId, JournalEntry $journalEntry): JournalEntry
    {
        $this->validateBelongsToStore($storeId, $journalEntry);

        return $this->repository->findForStore($journalEntry->id, $storeId)
            ?? abort(404);
    }

    /** @param array<string, mixed> $payload */
    public function createManual(int $storeId, array $payload): JournalEntry
    {
        $this->validateLinesAccounts($storeId, $payload['lines']);
        $totals = $this->calculateTotals($payload['lines']);
        $this->validateBalanced($payload['lines']);

        $journalEntry = DB::transaction(function () use ($storeId, $payload, $totals): JournalEntry {
            $journalDate = $payload['journal_date'];
            $journalEntry = $this->repository->create([
                'store_id' => $storeId,
                'journal_no' => $payload['journal_no'] ?? $this->numberService->generate($storeId, Carbon::parse($journalDate)),
                'journal_date' => $journalDate,
                'description' => $payload['description'],
                'status' => 'draft',
                'source_type' => 'manual',
                'source_id' => null,
                'reference_no' => $payload['reference_no'] ?? null,
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'created_by' => $this->currentUserId(),
                'updated_by' => $this->currentUserId(),
            ]);

            $this->insertLines($journalEntry, $storeId, $payload['lines']);

            return $journalEntry->fresh(['lines.account']);
        });

        if (($payload['status'] ?? 'draft') === 'posted') {
            return $this->post($storeId, $journalEntry);
        }

        return $journalEntry;
    }

    /** @param array<string, mixed> $payload */
    public function updateDraft(int $storeId, JournalEntry $journalEntry, array $payload): JournalEntry
    {
        $this->validateBelongsToStore($storeId, $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(409, 'Jurnal posted tidak boleh diubah.');
        }

        $this->validateLinesAccounts($storeId, $payload['lines']);
        $totals = $this->calculateTotals($payload['lines']);
        $this->validateBalanced($payload['lines']);

        return DB::transaction(function () use ($storeId, $journalEntry, $payload, $totals): JournalEntry {
            $journalEntry->lines()->delete();
            $journalEntry->update([
                'journal_date' => $payload['journal_date'],
                'description' => $payload['description'],
                'reference_no' => $payload['reference_no'] ?? null,
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'updated_by' => $this->currentUserId(),
            ]);

            $this->insertLines($journalEntry, $storeId, $payload['lines']);

            return $journalEntry->fresh(['lines.account']);
        });
    }

    public function deleteDraft(int $storeId, JournalEntry $journalEntry): void
    {
        $this->validateBelongsToStore($storeId, $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(409, 'Jurnal posted tidak boleh diubah.');
        }

        DB::transaction(fn () => $journalEntry->delete());
    }

    public function post(int $storeId, JournalEntry $journalEntry): JournalEntry
    {
        $this->validateBelongsToStore($storeId, $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(409, 'Hanya jurnal draft yang dapat diposting.');
        }

        if ($this->periodService->isPeriodLocked($storeId, $journalEntry->journal_date)) {
            abort(409, 'Periode jurnal sudah terkunci.');
        }

        $this->validateLinesAccounts($storeId, $journalEntry->lines->map(fn ($line): array => [
            'account_id' => $line->account_id,
            'store_id' => $line->store_id,
        ])->all());
        $this->validateBalanced($journalEntry->lines->toArray());

        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $journalEntry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $this->currentUserId(),
                'updated_by' => $this->currentUserId(),
            ]);

            return $journalEntry->fresh(['lines.account', 'postedBy']);
        });
    }

    public function reverse(int $storeId, JournalEntry $journalEntry, ?string $reason = null): JournalEntry
    {
        $this->validateBelongsToStore($storeId, $journalEntry);

        if ($journalEntry->status !== 'posted') {
            abort(409, 'Hanya jurnal posted yang dapat direversal.');
        }

        if ($journalEntry->reversed_entry_id !== null) {
            abort(409, 'Jurnal sudah pernah direversal.');
        }

        if ($this->periodService->isPeriodLocked($storeId, Carbon::now())) {
            abort(409, 'Periode jurnal sudah terkunci.');
        }

        return DB::transaction(function () use ($storeId, $journalEntry, $reason): JournalEntry {
            $journalDate = Carbon::now()->toDateString();
            $reversal = $this->repository->create([
                'store_id' => $storeId,
                'journal_no' => $this->numberService->generate($storeId, Carbon::now()),
                'journal_date' => $journalDate,
                'description' => trim('Reversal dari jurnal '.$journalEntry->journal_no.' '.($reason ?? '')),
                'status' => 'posted',
                'source_type' => 'journal_reversal',
                'source_id' => $journalEntry->id,
                'reference_no' => $journalEntry->reference_no,
                'total_debit' => $journalEntry->total_credit,
                'total_credit' => $journalEntry->total_debit,
                'posted_at' => now(),
                'posted_by' => $this->currentUserId(),
                'created_by' => $this->currentUserId(),
                'updated_by' => $this->currentUserId(),
            ]);

            foreach ($journalEntry->lines as $line) {
                $reversal->lines()->create([
                    'store_id' => $storeId,
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'line_order' => $line->line_order,
                ]);
            }

            $journalEntry->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $this->currentUserId(),
                'reversed_entry_id' => $reversal->id,
                'updated_by' => $this->currentUserId(),
            ]);

            return $reversal->fresh(['lines.account', 'postedBy']);
        });
    }

    public function validateBelongsToStore(int $storeId, JournalEntry $journalEntry): void
    {
        if ((int) $journalEntry->store_id !== $storeId) {
            abort(404);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{debit: float, credit: float}
     */
    public function calculateTotals(array $lines): array
    {
        return [
            'debit' => round(array_sum(array_map(fn (array $line): float => (float) ($line['debit'] ?? 0), $lines)), 2),
            'credit' => round(array_sum(array_map(fn (array $line): float => (float) ($line['credit'] ?? 0), $lines)), 2),
        ];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function validateBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            abort(422, 'Jurnal harus memiliki minimal dua baris.');
        }

        $totals = $this->calculateTotals($lines);

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                abort(422, 'Setiap baris hanya boleh memiliki debit atau kredit lebih dari nol.');
            }
        }

        if ($totals['debit'] <= 0 || $totals['credit'] <= 0 || $totals['debit'] !== $totals['credit']) {
            abort(422, 'Total debit dan kredit harus sama serta lebih dari nol.');
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    protected function validateLinesAccounts(int $storeId, array $lines): void
    {
        foreach ($lines as $line) {
            if ((int) ($line['store_id'] ?? $storeId) !== $storeId) {
                abort(422, 'Baris jurnal bukan milik toko aktif.');
            }
        }

        $accountIds = array_values(array_unique(array_map(fn (array $line): string => (string) $line['account_id'], $lines)));
        $validCount = Account::query()
            ->forStore($storeId)
            ->active()
            ->whereIn('id', $accountIds)
            ->count();

        if ($validCount !== count($accountIds)) {
            abort(422, 'Akun pada baris jurnal tidak aktif atau tidak valid.');
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    protected function insertLines(JournalEntry $journalEntry, int $storeId, array $lines): void
    {
        foreach (array_values($lines) as $index => $line) {
            $journalEntry->lines()->create([
                'store_id' => $storeId,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'line_order' => $index,
            ]);
        }
    }

    protected function currentUserId(): ?int
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
