<?php

namespace App\Models\Accounting;

use App\Models\Store;
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
 * @property string $journal_no
 * @property Carbon $journal_date
 * @property string|null $description
 * @property string $status
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $reference_no
 * @property string $total_debit
 * @property string $total_credit
 * @property Carbon|null $posted_at
 * @property int|null $posted_by
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversed_entry_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JournalEntry extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'store_id', 'journal_no', 'journal_date', 'description', 'status',
        'source_type', 'source_id', 'reference_no', 'total_debit', 'total_credit',
        'posted_at', 'posted_by', 'reversed_at', 'reversed_by', 'reversed_entry_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    /** @return HasMany<JournalEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_order');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
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

    /** @return BelongsTo<self, $this> */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
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
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBetweenDates(Builder $query, ?string $dateStart, ?string $dateEnd): Builder
    {
        return $query
            ->when($dateStart, fn (Builder $query, string $date) => $query->whereDate('journal_date', '>=', $date))
            ->when($dateEnd, fn (Builder $query, string $date) => $query->whereDate('journal_date', '<=', $date));
    }
}
