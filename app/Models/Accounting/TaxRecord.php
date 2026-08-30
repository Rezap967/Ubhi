<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $store_id
 * @property string $transaction_no
 * @property Carbon $tax_date
 * @property string|null $tax_rate_id
 * @property string $tax_type
 * @property string $tax_name
 * @property float $tax_base
 * @property float $tax_rate
 * @property float $tax_amount
 * @property string $status
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $tax_account_id
 * @property string|null $counter_account_id
 * @property string|null $journal_entry_id
 * @property string|null $reference_no
 * @property string|null $notes
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class TaxRecord extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'tax_records';

    protected $fillable = ['store_id', 'transaction_no', 'tax_date', 'tax_rate_id', 'tax_type', 'tax_name', 'tax_base', 'tax_rate', 'tax_amount', 'status', 'source_type', 'source_id', 'tax_account_id', 'counter_account_id', 'journal_entry_id', 'reference_no', 'notes', 'paid_at', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'created_by', 'updated_by'];

    protected $casts = ['tax_date' => 'date', 'tax_base' => 'decimal:2', 'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:2', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function taxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @param Builder<self> $query */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /** @param Builder<self> $query */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** @param Builder<self> $query */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
