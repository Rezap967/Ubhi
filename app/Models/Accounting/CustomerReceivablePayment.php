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
 * @property string $customer_receivable_id
 * @property string $payment_no
 * @property Carbon $payment_date
 * @property float $amount
 * @property string $cash_or_bank_account_id
 * @property string $payment_method
 * @property string|null $reference_no
 * @property string|null $notes
 * @property string|null $journal_entry_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class CustomerReceivablePayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'customer_receivable_payments';

    protected $fillable = [
        'store_id', 'customer_receivable_id', 'payment_no', 'payment_date',
        'amount', 'cash_or_bank_account_id', 'payment_method', 'reference_no',
        'notes', 'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<CustomerReceivable, $this> */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(CustomerReceivable::class, 'customer_receivable_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function cashOrBankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_or_bank_account_id');
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

    /** @param Builder<self> $query */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
