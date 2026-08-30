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
 * @property string $expense_no
 * @property Carbon $expense_date
 * @property string $description
 * @property string|null $category_id
 * @property string $expense_account_id
 * @property string $cash_or_bank_account_id
 * @property string $amount
 * @property string|null $tax_rate_id
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $payment_method
 * @property string|null $reference_no
 * @property string|null $notes
 * @property string $status
 * @property string|null $journal_entry_id
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ExpenseCategory|null $category
 * @property-read Account|null $expenseAccount
 * @property-read Account|null $cashOrBankAccount
 * @property-read JournalEntry|null $journalEntry
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $voidedBy
 */
class Expense extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'store_id', 'expense_no', 'expense_date', 'description', 'category_id',
        'expense_account_id', 'cash_or_bank_account_id', 'amount', 'tax_rate_id',
        'tax_amount', 'total_amount', 'payment_method', 'reference_no', 'notes',
        'status', 'journal_entry_id', 'voided_at', 'voided_by', 'void_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function cashOrBankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_or_bank_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
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

    /** @param Builder<self> $query */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    /** @param Builder<self> $query */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /** @param Builder<self> $query */
    public function scopeNotVoid(Builder $query): Builder
    {
        return $query->where('status', '!=', 'void');
    }
}
