<?php

namespace App\Models\Accounting;

use App\Models\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $store_id
 * @property string $receivable_no
 * @property int|null $customer_id
 * @property string $customer_name
 * @property Carbon $receivable_date
 * @property Carbon $due_date
 * @property string|null $description
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string $receivable_account_id
 * @property string $revenue_account_id
 * @property float $original_amount
 * @property float $paid_amount
 * @property float $outstanding_amount
 * @property string $status
 * @property string|null $journal_entry_id
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class CustomerReceivable extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'customer_receivables';

    protected $fillable = [
        'store_id', 'receivable_no', 'customer_id', 'customer_name',
        'receivable_date', 'due_date', 'description', 'source_type', 'source_id',
        'receivable_account_id', 'revenue_account_id', 'original_amount',
        'paid_amount', 'outstanding_amount', 'status', 'journal_entry_id',
        'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'receivable_date' => 'date',
        'due_date' => 'date',
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return HasMany<CustomerReceivablePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerReceivablePayment::class);
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
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @param Builder<self> $query */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
