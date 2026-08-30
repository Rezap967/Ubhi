<?php

namespace App\Models\Accounting;

use App\Models\Products\Supplier;
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
 * @property string $payable_no
 * @property int|null $supplier_id
 * @property string $supplier_name
 * @property Carbon $payable_date
 * @property Carbon $due_date
 * @property string|null $description
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string $expense_or_inventory_account_id
 * @property string $liability_account_id
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SupplierPayable extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'supplier_payables';

    protected $fillable = [
        'store_id',
        'payable_no',
        'supplier_id',
        'supplier_name',
        'payable_date',
        'due_date',
        'description',
        'source_type',
        'source_id',
        'expense_or_inventory_account_id',
        'liability_account_id',
        'original_amount',
        'paid_amount',
        'outstanding_amount',
        'status',
        'journal_entry_id',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payable_date' => 'date',
        'due_date' => 'date',
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'voided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return HasMany<SupplierPayablePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayablePayment::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function expenseOrInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_or_inventory_account_id');
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
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
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
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'partial', 'overdue']);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotVoid(Builder $query): Builder
    {
        return $query->where('status', '!=', 'void');
    }
}
