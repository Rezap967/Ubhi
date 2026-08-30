<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $journal_entry_id
 * @property int $store_id
 * @property string $account_id
 * @property string|null $description
 * @property string $debit
 * @property string $credit
 * @property int $line_order
 */
class JournalEntryLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'journal_entry_id', 'store_id', 'account_id', 'description',
        'debit', 'credit', 'line_order',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'line_order' => 'integer',
    ];

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOnlyPosted(Builder $query): Builder
    {
        return $query->whereHas('journalEntry', function (Builder $query): void {
            $query->posted();
        });
    }
}
