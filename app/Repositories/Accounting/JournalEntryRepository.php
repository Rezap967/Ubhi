<?php

namespace App\Repositories\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class JournalEntryRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, JournalEntry>
     */
    public function paginate(int $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->forStore($storeId)
            ->with(['lines.account', 'postedBy', 'reversedBy'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->whereRaw('LOWER(journal_no) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$search]);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date_start'] ?? null, fn ($query, string $date) => $query->whereDate('journal_date', '>=', $date))
            ->when($filters['date_end'] ?? null, fn ($query, string $date) => $query->whereDate('journal_date', '<=', $date))
            ->orderByDesc('journal_date')
            ->orderByDesc('journal_no')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, JournalEntry>
     */
    public function search(int $storeId, array $filters = [], int $limit = 20): Collection
    {
        return JournalEntry::query()
            ->forStore($storeId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->whereRaw('LOWER(journal_no) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$search]);
                });
            })
            ->orderByDesc('journal_date')
            ->orderByDesc('journal_no')
            ->limit($limit)
            ->get();
    }

    public function findForStore(string $id, int $storeId): ?JournalEntry
    {
        return JournalEntry::query()
            ->forStore($storeId)
            ->with(['lines.account', 'postedBy', 'reversedBy', 'reversedEntry'])
            ->whereKey($id)
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): JournalEntry
    {
        return JournalEntry::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(JournalEntry $journalEntry, array $data): JournalEntry
    {
        $journalEntry->update($data);

        return $journalEntry->fresh(['lines.account', 'postedBy', 'reversedBy', 'reversedEntry']);
    }
}
